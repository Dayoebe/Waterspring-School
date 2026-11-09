<?php

namespace Tests\Feature\Safety;

use App\Livewire\Cbt\CbtManagement;
use App\Livewire\Fees\FeeInvoiceDetail;
use App\Livewire\Students\PromoteStudents;
use App\Models\AcademicYear;
use App\Models\Assessment\Assessment;
use App\Models\ClassGroup;
use App\Models\Fee;
use App\Models\FeeCategory;
use App\Models\FeeInvoice;
use App\Models\FeeInvoiceRecord;
use App\Models\MyClass;
use App\Models\School;
use App\Models\Semester;
use App\Models\StudentRecord;
use App\Models\StudentSupportCase;
use App\Models\Subject;
use App\Models\User;
use App\Support\PermissionMatrix;
use App\Support\ResultPublicationStatus;
use App\Traits\ResolvesAccessibleStudents;
use App\Traits\RestrictsTeacherResultUploads;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CriticalWorkflowSafetyTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    public function test_every_configured_role_has_its_expected_dashboard_boundary(): void
    {
        $school = $this->school('ROLE');
        $roles = collect(array_keys(PermissionMatrix::roles()))
            ->mapWithKeys(fn (string $roleName) => [
                $roleName => Role::findOrCreate($roleName, 'web'),
            ]);

        foreach (PermissionMatrix::roles() as $roleName => $permissions) {
            $user = $this->user($school, $roleName);
            $role = $roles->get($roleName);
            $user->assignRole($role);

            $shouldAccessDashboard = $permissions === ['*'] || in_array('view dashboard', $permissions, true);
            if ($shouldAccessDashboard && $permissions !== ['*']) {
                $role->givePermissionTo(Permission::findOrCreate('view dashboard', 'web'));
            }

            if ($roleName === 'student') {
                [$academicYear, $class] = $this->academicStructure($school, 'ROLE');
                $this->studentRecord($user, $academicYear, $class);
            }

            $response = $this->actingAs($user)->get(route('dashboard'));

            $shouldAccessDashboard
                ? $response->assertOk()
                : $response->assertForbidden();
        }
    }

    public function test_cbt_library_query_keeps_current_school_assessments_visible(): void
    {
        $school = $this->school('CBT-LIBRARY');
        [$academicYear, $class, $semester] = $this->academicStructure($school, 'CBT-LIBRARY');
        $manager = $this->userWithRole($school, 'super-admin', 'cbt-library-manager');
        $subject = Subject::query()->create([
            'name' => 'CBT Library Subject',
            'short_name' => 'CBTLIB',
            'school_id' => $school->id,
            'my_class_id' => $class->id,
            'is_general' => false,
            'is_legacy' => false,
        ]);

        $this->actingAs($manager);
        Assessment::query()->create([
            'course_id' => $class->id,
            'lesson_id' => $subject->id,
            'academic_year_id' => $academicYear->id,
            'semester_id' => $semester->id,
            'title' => 'Visible CBT library assessment',
            'type' => 'quiz',
            'pass_percentage' => 70,
            'estimated_duration_minutes' => 30,
            'max_score' => 100,
        ]);

        Livewire::actingAs($manager)
            ->test(CbtManagement::class)
            ->assertSee('Visible CBT library assessment');
    }

    public function test_parent_student_access_excludes_unlinked_and_cross_school_children(): void
    {
        $schoolA = $this->school('PARENT-A');
        $schoolB = $this->school('PARENT-B');
        $parent = $this->userWithRole($schoolA, 'parent', 'parent');
        $ownChild = $this->userWithRole($schoolA, 'student', 'own-child');
        $otherChild = $this->userWithRole($schoolB, 'student', 'other-child');

        DB::table('parent_records')->insert([
            ['user_id' => $parent->id, 'student_id' => $ownChild->id, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $parent->id, 'student_id' => $otherChild->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($parent);

        $this->assertSame([$ownChild->id], app(AccessibleStudentHarness::class)->studentIds());
    }

    public function test_fee_invoice_details_cannot_be_opened_across_schools(): void
    {
        $schoolA = $this->school('FEE-A');
        $schoolB = $this->school('FEE-B');
        $manager = $this->userWithPermission($schoolA, 'update fee invoice', 'fee-manager');
        $studentB = $this->userWithRole($schoolB, 'student', 'fee-student');
        $invoiceB = $this->invoice($studentB);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($manager)
            ->test(FeeInvoiceDetail::class, ['feeInvoiceId' => $invoiceB->id]);
    }

    public function test_fee_payments_reject_overpayment_and_persist_valid_amounts(): void
    {
        $school = $this->school('PAY');
        $manager = $this->userWithPermission($school, 'update fee invoice', 'cashier');
        $student = $this->userWithRole($school, 'student', 'payer');
        $invoice = $this->invoice($student);
        $category = FeeCategory::query()->create(['school_id' => $school->id, 'name' => 'Tuition']);
        $fee = Fee::query()->create(['fee_category_id' => $category->id, 'name' => 'Term tuition']);
        $record = FeeInvoiceRecord::query()->create([
            'fee_invoice_id' => $invoice->id,
            'fee_id' => $fee->id,
            'amount' => 100,
            'waiver' => 0,
            'fine' => 0,
        ]);

        Livewire::actingAs($manager)
            ->test(FeeInvoiceDetail::class, ['feeInvoiceId' => $invoice->id])
            ->set('payingRecordId', $record->id)
            ->set('paymentAmount', 101)
            ->call('addPayment');

        $this->assertSame('0.00', (string) $record->fresh()->paid->getAmount());

        Livewire::actingAs($manager)
            ->test(FeeInvoiceDetail::class, ['feeInvoiceId' => $invoice->id])
            ->set('payingRecordId', $record->id)
            ->set('paymentAmount', 40)
            ->call('addPayment');

        $this->assertSame('40.00', (string) $record->fresh()->paid->getAmount());
    }

    public function test_result_publication_is_scoped_by_school_and_can_be_revoked(): void
    {
        $schoolA = $this->school('RESULT-A');
        $schoolB = $this->school('RESULT-B');
        [$yearA, , $semesterA] = $this->academicStructure($schoolA, 'RESULT-A');
        [$yearB, , $semesterB] = $this->academicStructure($schoolB, 'RESULT-B');
        $publisher = $this->user($schoolA, 'publisher');

        ResultPublicationStatus::setTermPublished($schoolA->id, $yearA->id, $semesterA->id, true, $publisher->id);

        $this->assertTrue(ResultPublicationStatus::termIsPublished($schoolA->id, $yearA->id, $semesterA->id));
        $this->assertFalse(ResultPublicationStatus::termIsPublished($schoolB->id, $yearB->id, $semesterB->id));

        ResultPublicationStatus::setTermPublished($schoolA->id, $yearA->id, $semesterA->id, false, $publisher->id);

        $this->assertFalse(ResultPublicationStatus::termIsPublished($schoolA->id, $yearA->id, $semesterA->id));
    }

    public function test_promotion_cannot_target_another_schools_class(): void
    {
        $schoolA = $this->school('PROMOTE-A');
        $schoolB = $this->school('PROMOTE-B');
        [$yearA, $classA] = $this->academicStructure($schoolA, 'PROMOTE-A');
        [, $classB] = $this->academicStructure($schoolB, 'PROMOTE-B');
        $manager = $this->userWithPermission($schoolA, 'promote student', 'promoter');
        $student = $this->userWithRole($schoolA, 'student', 'promote-student');
        $record = $this->studentRecord($student, $yearA, $classA);

        Livewire::actingAs($manager)
            ->test(PromoteStudents::class)
            ->set('oldClass', $classA->id)
            ->set('newClass', $classB->id)
            ->set('fromAcademicYear', $yearA->id)
            ->set('toAcademicYear', $yearA->id)
            ->call('loadStudents')
            ->assertSet('students', []);

        $this->assertDatabaseHas('academic_year_student_record', [
            'student_record_id' => $record->id,
            'academic_year_id' => $yearA->id,
            'my_class_id' => $classA->id,
        ]);
        $this->assertDatabaseCount('promotions', 0);
    }

    public function test_teacher_upload_scope_requires_assigned_class_subject_and_same_school_student(): void
    {
        $schoolA = $this->school('UPLOAD-A');
        $schoolB = $this->school('UPLOAD-B');
        [$yearA, $classA] = $this->academicStructure($schoolA, 'UPLOAD-A');
        [$yearB, $classB] = $this->academicStructure($schoolB, 'UPLOAD-B');
        $teacher = $this->userWithRole($schoolA, 'teacher', 'upload-teacher');
        $studentA = $this->userWithRole($schoolA, 'student', 'upload-student-a');
        $studentB = $this->userWithRole($schoolB, 'student', 'upload-student-b');
        $recordA = $this->studentRecord($studentA, $yearA, $classA);
        $recordB = $this->studentRecord($studentB, $yearB, $classB);
        $subject = Subject::query()->create([
            'name' => 'Mathematics '.$this->sequence,
            'short_name' => 'MTH'.$this->sequence,
            'school_id' => $schoolA->id,
            'my_class_id' => $classA->id,
            'is_general' => false,
            'is_legacy' => false,
        ]);

        DB::table('class_teacher')->insert([
            'class_id' => $classA->id,
            'teacher_id' => $teacher->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('subject_teacher')->insert([
            'subject_id' => $subject->id,
            'user_id' => $teacher->id,
            'my_class_id' => $classA->id,
            'school_id' => $schoolA->id,
            'is_general' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($teacher);
        $authorizer = app(ResultUploadHarness::class);

        $this->assertTrue($authorizer->canUploadClass($classA->id, $yearA->id));
        $this->assertTrue($authorizer->canUploadSubject($subject->id, $classA->id, $yearA->id));
        $this->assertTrue($authorizer->canUploadStudent($recordA->id, $classA->id, $yearA->id));
        $this->assertFalse($authorizer->canUploadStudent($recordB->id, $classB->id, $yearB->id));
    }

    public function test_student_support_cases_are_invisible_across_school_contexts(): void
    {
        $schoolA = $this->school('SUPPORT-A');
        $schoolB = $this->school('SUPPORT-B');
        $manager = $this->userWithPermission($schoolA, 'manage student support', 'support-manager');

        $caseA = $this->supportCase($schoolA, 'A');
        $caseB = $this->supportCase($schoolB, 'B');

        $this->actingAs($manager);

        $this->assertSame([$caseA->id], StudentSupportCase::query()->pluck('id')->all());
        $this->assertSame(2, StudentSupportCase::query()->withoutGlobalScope('school')->count());
        $this->assertNotSame($caseA->id, $caseB->id);
    }

    private function school(string $suffix): School
    {
        $this->sequence++;

        return School::query()->create([
            'name' => "Safety School {$suffix}",
            'code' => 'SAFE-'.$suffix.'-'.$this->sequence,
        ]);
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

    private function userWithRole(School $school, string $roleName, string $label): User
    {
        $user = $this->user($school, $label);
        $user->assignRole(Role::findOrCreate($roleName, 'web'));

        return $user;
    }

    private function userWithPermission(School $school, string $permissionName, string $label): User
    {
        $user = $this->user($school, $label);
        $user->givePermissionTo(Permission::findOrCreate($permissionName, 'web'));

        return $user;
    }

    /**
     * @return array{AcademicYear, MyClass, Semester}
     */
    private function academicStructure(School $school, string $suffix): array
    {
        $this->sequence++;
        $year = AcademicYear::query()->create([
            'start_year' => (string) (2030 + $this->sequence),
            'stop_year' => (string) (2031 + $this->sequence),
            'school_id' => $school->id,
        ]);
        $semester = Semester::query()->create([
            'name' => 'First Term '.$suffix,
            'academic_year_id' => $year->id,
            'school_id' => $school->id,
        ]);
        $group = ClassGroup::query()->create([
            'name' => 'Class Group '.$suffix,
            'school_id' => $school->id,
        ]);
        $class = MyClass::query()->create([
            'name' => 'Class '.$suffix,
            'class_group_id' => $group->id,
        ]);
        $school->update(['academic_year_id' => $year->id, 'semester_id' => $semester->id]);

        return [$year, $class, $semester];
    }

    private function studentRecord(User $student, AcademicYear $year, MyClass $class): StudentRecord
    {
        $record = StudentRecord::query()->create([
            'user_id' => $student->id,
            'admission_number' => 'SAFE-'.$student->id,
            'my_class_id' => $class->id,
            'is_graduated' => false,
        ]);
        DB::table('academic_year_student_record')->insert([
            'academic_year_id' => $year->id,
            'student_record_id' => $record->id,
            'my_class_id' => $class->id,
            'section_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $record;
    }

    private function invoice(User $student): FeeInvoice
    {
        return FeeInvoice::query()->create([
            'name' => 'Safety invoice '.$student->id,
            'user_id' => $student->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addMonth()->toDateString(),
        ]);
    }

    private function supportCase(School $school, string $suffix): StudentSupportCase
    {
        $student = $this->user($school, "support-student-{$suffix}");
        $parent = $this->user($school, "support-parent-{$suffix}");
        $teacher = $this->user($school, "support-teacher-{$suffix}");

        return StudentSupportCase::query()->withoutGlobalScope('school')->create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'parent_id' => $parent->id,
            'lead_teacher_id' => $teacher->id,
            'opened_by' => $teacher->id,
            'source_type' => 'manual',
            'category' => 'academic',
            'priority' => 'medium',
            'title' => "Support case {$suffix}",
            'concern' => 'A documented concern for safety testing.',
            'goal' => 'Confirm tenant isolation.',
            'status' => 'monitoring',
            'review_on' => now()->addWeek()->toDateString(),
        ]);
    }
}

class AccessibleStudentHarness
{
    use ResolvesAccessibleStudents;

    /**
     * @return list<int>
     */
    public function studentIds(): array
    {
        return $this->portalAccessibleStudentUserIds()->all();
    }
}

class ResultUploadHarness
{
    use RestrictsTeacherResultUploads;

    public function canUploadClass(int $classId, int $academicYearId): bool
    {
        return $this->currentUserCanUploadResultClass($classId, $academicYearId);
    }

    public function canUploadSubject(int $subjectId, int $classId, int $academicYearId): bool
    {
        return $this->currentUserCanUploadResultSubject($subjectId, $classId, $academicYearId);
    }

    public function canUploadStudent(int $studentRecordId, int $classId, int $academicYearId): bool
    {
        return $this->currentUserCanUploadResultStudent($studentRecordId, $classId, $academicYearId);
    }
}
