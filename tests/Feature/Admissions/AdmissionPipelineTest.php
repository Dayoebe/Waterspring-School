<?php

namespace Tests\Feature\Admissions;

use App\Models\AdmissionRegistration;
use App\Models\ClassGroup;
use App\Models\MyClass;
use App\Models\School;
use App\Models\User;
use App\Services\AdmissionPipeline;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdmissionPipelineTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    public function test_full_pipeline_converts_an_accepted_fee_confirmed_offer_into_linked_accounts(): void
    {
        [$school, $class] = $this->schoolAndClass('FULL');
        $staff = $this->user($school, 'admissions-officer');
        $admission = $this->admission($school, $class, 'reviewed');
        $pipeline = app(AdmissionPipeline::class);

        $interview = $pipeline->scheduleInterview($admission, $staff, [
            'scheduled_at' => now()->addDay(),
            'duration_minutes' => 45,
            'mode' => 'video',
            'location' => 'https://meet.example.test/admission',
        ]);
        $pipeline->completeInterview($interview, $staff, 'recommended', 'Ready for an offer.');

        $token = $pipeline->makeOffer($admission->fresh(), $staff, 'Offer approved.', 14);
        $this->assertNotSame($token, $admission->fresh()->acceptance_token_hash);
        $this->assertSame(hash('sha256', $token), $admission->fresh()->acceptance_token_hash);

        Mail::fake();
        $this->post(route('admission.offer.accept', [$admission->reference_no, $token]), [
            'accepted_by_name' => 'Grace Guardian',
        ])->assertRedirect();

        $accepted = $admission->fresh();
        $this->assertSame('accepted', $accepted->status);
        $this->assertSame('Grace Guardian', $accepted->accepted_by_name);
        $this->assertNull($accepted->acceptance_token_hash);

        $confirmation = $pipeline->confirmFee($accepted, $staff, [
            'amount' => '25000.00',
            'payment_method' => 'bank_transfer',
            'reference' => 'ADM-FEE-001',
            'notes' => 'Confirmed against the school bank statement.',
        ]);
        $result = $pipeline->convertToStudentAndParent($accepted->fresh(), $staff);

        $this->assertSame(2500000, (int) $confirmation->getRawOriginal('amount'));
        $this->assertSame('enrolled', $admission->fresh()->status);
        $this->assertSame($result['student']->id, $admission->fresh()->enrolled_user_id);
        $this->assertSame($result['parent']->id, $admission->fresh()->enrolled_parent_id);
        $this->assertDatabaseHas('parent_records', [
            'user_id' => $result['parent']->id,
            'student_id' => $result['student']->id,
        ]);
        $this->assertTrue($result['student']->hasRole('student'));
        $this->assertTrue($result['parent']->hasRole('parent'));
        $this->assertDatabaseHas('admission_status_histories', [
            'admission_registration_id' => $admission->id,
            'from_status' => 'accepted',
            'to_status' => 'enrolled',
            'changed_by' => $staff->id,
        ]);
    }

    public function test_enrolment_requires_an_accepted_offer_and_fee_confirmation(): void
    {
        [$school, $class] = $this->schoolAndClass('GATE');
        $staff = $this->user($school, 'admissions-gatekeeper');
        $admission = $this->admission($school, $class, 'accepted');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Confirm the admission fee before enrolment.');

        app(AdmissionPipeline::class)->convertToStudentAndParent($admission, $staff);
    }

    public function test_pipeline_rejects_cross_school_actions(): void
    {
        [$schoolA, $classA] = $this->schoolAndClass('A');
        [$schoolB] = $this->schoolAndClass('B');
        $admission = $this->admission($schoolA, $classA, 'reviewed');
        $otherStaff = $this->user($schoolB, 'other-school-officer');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Application and staff member must belong to the same school.');

        app(AdmissionPipeline::class)->makeOffer($admission, $otherStaff);
    }

    public function test_internal_notes_and_fee_confirmations_are_immutable(): void
    {
        [$school, $class] = $this->schoolAndClass('IMMUTABLE');
        $staff = $this->user($school, 'immutable-officer');
        $admission = $this->admission($school, $class, 'accepted');
        $pipeline = app(AdmissionPipeline::class);

        $note = $pipeline->addInternalNote($admission, $staff, 'Original internal assessment.');
        $fee = $pipeline->confirmFee($admission, $staff, [
            'amount' => 0,
            'payment_method' => 'waived',
            'notes' => 'Scholarship waiver.',
        ]);

        $this->assertImmutable(
            fn () => $note->update(['note' => 'Rewritten']),
            'Admission notes are append-only.'
        );
        $this->assertImmutable(
            fn () => $fee->update(['reference' => 'Changed']),
            'Admission fee confirmations are immutable.'
        );
        $this->assertDatabaseCount('admission_internal_notes', 1);
        $this->assertDatabaseCount('admission_fee_confirmations', 1);
    }

    public function test_private_documents_are_available_only_to_authorised_staff_in_the_same_school(): void
    {
        Storage::fake('local');
        [$schoolA, $classA] = $this->schoolAndClass('DOC-A');
        [$schoolB] = $this->schoolAndClass('DOC-B');
        $admission = $this->admission($schoolA, $classA, 'pending');
        Storage::disk('local')->put('admissions/private/report.pdf', 'private report');
        $document = $admission->documents()->create([
            'school_id' => $schoolA->id,
            'category' => 'previous_report',
            'original_name' => 'report.pdf',
            'path' => 'admissions/private/report.pdf',
            'disk' => 'local',
            'mime_type' => 'application/pdf',
        ]);

        $staffA = $this->user($schoolA, 'document-reader');
        $staffA->givePermissionTo(Permission::findOrCreate('read admission registration', 'web'));
        $this->actingAs($staffA)->get(route('admissions.documents.download', $document))->assertOk();

        $staffB = $this->user($schoolB, 'cross-school-reader');
        $staffB->givePermissionTo(Permission::findOrCreate('read admission registration', 'web'));
        $this->actingAs($staffB)->get(route('admissions.documents.download', $document))->assertNotFound();
    }

    private function schoolAndClass(string $suffix): array
    {
        $this->sequence++;
        $school = School::query()->create([
            'name' => "Admissions School {$suffix}",
            'code' => "ADM-{$suffix}-{$this->sequence}",
            'initials' => 'ADM',
        ]);
        $group = ClassGroup::query()->create([
            'name' => "Admissions Group {$suffix}",
            'school_id' => $school->id,
        ]);
        $class = MyClass::query()->create([
            'name' => "Admissions Class {$suffix}",
            'class_group_id' => $group->id,
        ]);

        return [$school, $class];
    }

    private function user(School $school, string $label): User
    {
        $this->sequence++;

        return User::query()->forceCreate([
            'name' => ucfirst(str_replace('-', ' ', $label)),
            'email' => "{$label}-{$this->sequence}@example.test",
            'email_verified_at' => now(),
            'password' => bcrypt('Password1!'),
            'school_id' => $school->id,
            'locked' => false,
            'requires_password_change' => false,
        ]);
    }

    private function admission(School $school, MyClass $class, string $status): AdmissionRegistration
    {
        $this->sequence++;

        return AdmissionRegistration::query()->create([
            'school_id' => $school->id,
            'my_class_id' => $class->id,
            'reference_no' => 'ADM-REF-'.$this->sequence,
            'student_name' => 'Ada Student '.$this->sequence,
            'student_email' => "student-{$this->sequence}@example.test",
            'gender' => 'female',
            'birthday' => '2014-04-05',
            'guardian_name' => 'Grace Guardian',
            'guardian_phone' => '+2348000000000',
            'guardian_email' => "guardian-{$this->sequence}@example.test",
            'guardian_relationship' => 'Mother',
            'address' => 'Admissions test address',
            'status' => $status,
        ]);
    }

    private function assertImmutable(callable $operation, string $message): void
    {
        try {
            $operation();
            $this->fail('Expected immutable model operation to fail.');
        } catch (LogicException $exception) {
            $this->assertSame($message, $exception->getMessage());
        }
    }
}
