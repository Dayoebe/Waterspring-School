<?php

namespace App\Services;

use App\Models\AdmissionFeeConfirmation;
use App\Models\AdmissionInternalNote;
use App\Models\AdmissionInterview;
use App\Models\AdmissionRegistration;
use App\Models\AdmissionStatusHistory;
use App\Models\MyClass;
use App\Models\ParentRecord;
use App\Models\School;
use App\Models\Section;
use App\Models\StudentRecord;
use App\Models\User;
use Brick\Money\Money;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AdmissionPipeline
{
    public const STATUSES = [
        'pending', 'reviewed', 'interview_scheduled', 'interviewed', 'offered',
        'accepted', 'declined', 'enrolled',
    ];

    public function addInternalNote(AdmissionRegistration $admission, User $author, string $note): AdmissionInternalNote
    {
        $this->assertSameSchool($admission, $author);
        $note = trim($note);
        if (mb_strlen($note) < 3) {
            throw new DomainException('Internal note must contain at least three characters.');
        }

        return AdmissionInternalNote::query()->create([
            'admission_registration_id' => $admission->id,
            'school_id' => $admission->school_id,
            'note' => $note,
            'author_id' => $author->id,
            'author_name' => $author->name,
        ]);
    }

    public function scheduleInterview(AdmissionRegistration $admission, User $actor, array $data): AdmissionInterview
    {
        $this->assertSameSchool($admission, $actor);
        if (! in_array($admission->status, ['pending', 'reviewed', 'interview_scheduled'], true)) {
            throw new DomainException('An interview cannot be scheduled at this stage.');
        }

        return DB::transaction(function () use ($admission, $actor, $data): AdmissionInterview {
            $interview = AdmissionInterview::query()->create([
                'admission_registration_id' => $admission->id,
                'school_id' => $admission->school_id,
                'scheduled_at' => $data['scheduled_at'],
                'duration_minutes' => $data['duration_minutes'] ?? 30,
                'mode' => $data['mode'] ?? 'in_person',
                'location' => $this->nullable($data['location'] ?? null),
                'interviewer_id' => $data['interviewer_id'] ?? $actor->id,
                'status' => 'scheduled',
                'created_by' => $actor->id,
            ]);
            $this->transition($admission, 'interview_scheduled', $actor, 'Interview scheduled.');

            return $interview;
        });
    }

    public function completeInterview(AdmissionInterview $interview, User $actor, string $outcome, ?string $notes): void
    {
        if ((int) $interview->school_id !== (int) $actor->school_id) {
            throw new DomainException('Interview and reviewer must belong to the same school.');
        }
        if (! in_array($outcome, ['recommended', 'hold', 'not_recommended'], true)) {
            throw new DomainException('Select a supported interview outcome.');
        }
        if ($interview->status !== 'scheduled') {
            throw new DomainException('This interview outcome has already been recorded.');
        }

        DB::transaction(function () use ($interview, $actor, $outcome, $notes): void {
            $interview->update([
                'status' => 'completed',
                'outcome' => $outcome,
                'notes' => $this->nullable($notes),
                'completed_at' => now(),
            ]);
            $this->transition($interview->admissionRegistration, 'interviewed', $actor, 'Interview completed: '.str_replace('_', ' ', $outcome).'.');
        });
    }

    public function makeOffer(AdmissionRegistration $admission, User $actor, ?string $note = null, int $validDays = 14): string
    {
        $this->assertSameSchool($admission, $actor);
        if (! in_array($admission->status, ['reviewed', 'interviewed'], true)) {
            throw new DomainException('Only reviewed or interviewed applications can receive an offer.');
        }

        $token = Str::random(64);
        DB::transaction(function () use ($admission, $actor, $note, $validDays, $token): void {
            $admission->update([
                'offered_at' => now(),
                'offered_by' => $actor->id,
                'offer_expires_at' => now()->addDays(max(1, $validDays)),
                'acceptance_token_hash' => hash('sha256', $token),
            ]);
            $this->transition($admission, 'offered', $actor, $this->nullable($note) ?: 'Admission offer issued.');
        });

        return $token;
    }

    public function acceptOffer(AdmissionRegistration $admission, string $token, string $acceptedBy, ?string $ip): void
    {
        if ($admission->status !== 'offered' || ! $admission->acceptance_token_hash) {
            throw new DomainException('This offer is not available for acceptance.');
        }
        if ($admission->offer_expires_at?->isPast()) {
            throw new DomainException('This admission offer has expired.');
        }
        if (! hash_equals($admission->acceptance_token_hash, hash('sha256', $token))) {
            throw new DomainException('The admission offer link is invalid.');
        }

        DB::transaction(function () use ($admission, $acceptedBy, $ip): void {
            $admission->update([
                'status' => 'accepted',
                'accepted_at' => now(),
                'accepted_by_name' => trim($acceptedBy),
                'accepted_ip' => $ip,
                'acceptance_token_hash' => null,
                'processed_at' => now(),
            ]);
            AdmissionStatusHistory::query()->create([
                'admission_registration_id' => $admission->id,
                'school_id' => $admission->school_id,
                'from_status' => 'offered',
                'to_status' => 'accepted',
                'note' => 'Offer accepted by '.trim($acceptedBy).'.',
                'changed_by' => null,
                'changed_at' => now(),
            ]);
        });
    }

    public function decline(AdmissionRegistration $admission, User $actor, string $reason): void
    {
        $this->assertSameSchool($admission, $actor);
        if (in_array($admission->status, ['accepted', 'declined', 'enrolled'], true)) {
            throw new DomainException('Accepted, declined, or enrolled applications cannot be declined.');
        }
        $this->transition($admission, 'declined', $actor, trim($reason) ?: 'Application declined.');
    }

    public function confirmFee(AdmissionRegistration $admission, User $actor, array $data): AdmissionFeeConfirmation
    {
        $this->assertSameSchool($admission, $actor);
        if ($admission->status !== 'accepted') {
            throw new DomainException('The offer must be accepted before confirming the admission fee.');
        }
        if ($admission->feeConfirmation()->exists()) {
            throw new DomainException('The admission fee has already been confirmed.');
        }

        $amount = Money::of((string) ($data['amount'] ?? 0), config('app.currency'));
        if ($amount->isNegative()) {
            throw new DomainException('Admission fee amount cannot be negative.');
        }

        return AdmissionFeeConfirmation::query()->create([
            'admission_registration_id' => $admission->id,
            'school_id' => $admission->school_id,
            'amount' => $amount,
            'currency' => strtoupper((string) config('app.currency')),
            'payment_method' => $data['payment_method'],
            'reference' => $this->nullable($data['reference'] ?? null),
            'notes' => $this->nullable($data['notes'] ?? null),
            'confirmed_by' => $actor->id,
            'confirmer_name' => $actor->name,
            'confirmed_at' => now(),
        ]);
    }

    public function convertToStudentAndParent(AdmissionRegistration $admission, User $actor): array
    {
        $this->assertSameSchool($admission, $actor);

        return DB::transaction(function () use ($admission, $actor): array {
            $locked = AdmissionRegistration::query()->lockForUpdate()->findOrFail($admission->id);
            if ($locked->status !== 'accepted') {
                throw new DomainException('Only accepted applications can be enrolled.');
            }
            if (! $locked->feeConfirmation()->exists()) {
                throw new DomainException('Confirm the admission fee before enrolment.');
            }
            if ($locked->enrolled_user_id || $locked->enrolled_student_record_id) {
                throw new DomainException('This application has already been converted.');
            }

            $school = School::query()->findOrFail($locked->school_id);
            $this->assertClassScope($locked);
            [$student, $record, $studentPassword] = $this->createStudent($locked, $school);
            $parent = $this->resolveOrCreateParent($locked, $school);
            ParentRecord::query()->firstOrCreate(['user_id' => $parent->id, 'student_id' => $student->id]);

            $locked->update([
                'status' => 'enrolled',
                'processed_by' => $actor->id,
                'processed_at' => now(),
                'enrolled_user_id' => $student->id,
                'enrolled_student_record_id' => $record->id,
                'enrolled_parent_id' => $parent->id,
                'enrolled_at' => now(),
            ]);
            AdmissionStatusHistory::query()->create([
                'admission_registration_id' => $locked->id,
                'school_id' => $locked->school_id,
                'from_status' => 'accepted',
                'to_status' => 'enrolled',
                'note' => 'Student and parent records created and linked.',
                'changed_by' => $actor->id,
                'changed_at' => now(),
            ]);

            return ['student' => $student, 'student_record' => $record, 'parent' => $parent, 'student_password' => $studentPassword];
        }, 3);
    }

    private function createStudent(AdmissionRegistration $admission, School $school): array
    {
        $email = $this->uniqueEmail($admission->student_email, $admission->student_name, $admission->reference_no, $school, 'student');
        $password = $this->lastName($admission->student_name);
        $student = User::query()->create([
            'name' => $admission->student_name,
            'email' => $email,
            'email_is_placeholder' => str_ends_with($email, '.admission.local'),
            'password' => Hash::make($password),
            'requires_password_change' => true,
            'gender' => $admission->gender,
            'birthday' => $admission->birthday,
            'phone' => $admission->guardian_phone,
            'address' => $admission->address,
            'school_id' => $admission->school_id,
        ]);
        $student->assignRole(Role::findOrCreate('student', 'web'));
        $record = $student->studentRecord()->create([
            'my_class_id' => $admission->my_class_id,
            'section_id' => $admission->section_id,
            'admission_number' => $this->admissionNumber($school),
            'admission_date' => now(),
        ]);
        if ($school->academicYear) {
            $record->academicYears()->syncWithoutDetaching([$school->academicYear->id => [
                'my_class_id' => $admission->my_class_id,
                'section_id' => $admission->section_id,
            ]]);
        }
        $record->assignSubjectsAutomatically();

        return [$student, $record, $password];
    }

    private function resolveOrCreateParent(AdmissionRegistration $admission, School $school): User
    {
        $email = $this->uniqueEmail($admission->guardian_email, $admission->guardian_name, $admission->reference_no, $school, 'parent', true);
        $existing = User::query()->where('email', $email)->first();
        if ($existing) {
            if ((int) $existing->school_id !== (int) $school->id) {
                throw new DomainException('Guardian email already belongs to another school.');
            }
            $existing->assignRole(Role::findOrCreate('parent', 'web'));

            return $existing;
        }

        $parent = User::query()->create([
            'name' => $admission->guardian_name,
            'email' => $email,
            'email_is_placeholder' => str_ends_with($email, '.admission.local'),
            'password' => Hash::make(Str::password(16)),
            'requires_password_change' => true,
            'phone' => $admission->guardian_phone,
            'address' => $admission->address,
            'school_id' => $school->id,
        ]);
        $parent->assignRole(Role::findOrCreate('parent', 'web'));

        return $parent;
    }

    private function uniqueEmail(?string $preferred, string $name, string $reference, School $school, string $type, bool $allowExisting = false): string
    {
        $preferred = strtolower(trim((string) $preferred));
        if ($preferred !== '') {
            if (! $allowExisting && User::query()->where('email', $preferred)->exists()) {
                throw new DomainException(ucfirst($type).' email already exists.');
            }

            return $preferred;
        }

        $base = Str::slug($name) ?: $type;
        $schoolPart = Str::slug($school->initials ?: $school->name) ?: 'school';
        $email = "{$base}.".strtolower($reference).".{$type}@{$schoolPart}.admission.local";
        $counter = 1;
        while (User::query()->where('email', $email)->exists()) {
            $email = "{$base}.".strtolower($reference).".{$type}.{$counter}@{$schoolPart}.admission.local";
            $counter++;
        }

        return $email;
    }

    private function transition(AdmissionRegistration $admission, string $status, User $actor, ?string $note): void
    {
        $from = $admission->status;
        $admission->update(['status' => $status, 'processed_by' => $actor->id, 'processed_at' => now()]);
        if ($from !== $status) {
            AdmissionStatusHistory::query()->create([
                'admission_registration_id' => $admission->id,
                'school_id' => $admission->school_id,
                'from_status' => $from,
                'to_status' => $status,
                'note' => $note,
                'changed_by' => $actor->id,
                'changed_at' => now(),
            ]);
        }
    }

    private function assertSameSchool(AdmissionRegistration $admission, User $actor): void
    {
        if ((int) $admission->school_id !== (int) $actor->school_id) {
            throw new DomainException('Application and staff member must belong to the same school.');
        }
    }

    private function assertClassScope(AdmissionRegistration $admission): void
    {
        $validClass = MyClass::query()->whereKey($admission->my_class_id)
            ->whereHas('classGroup', fn ($query) => $query->where('school_id', $admission->school_id))->exists();
        $validSection = ! $admission->section_id || Section::query()->whereKey($admission->section_id)
            ->where('my_class_id', $admission->my_class_id)->exists();
        if (! $validClass || ! $validSection) {
            throw new DomainException('The selected class or section is outside the application school.');
        }
    }

    private function admissionNumber(School $school): string
    {
        do {
            $number = strtoupper($school->initials ?: 'SCH').'/'.now()->format('y').'/'.random_int(100000, 999999);
        } while (StudentRecord::query()->where('admission_number', $number)->exists());

        return $number;
    }

    private function lastName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $last = (string) end($parts);
        if ($last === '') {
            throw new DomainException('A valid student name is required.');
        }

        return $last;
    }

    private function nullable(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
