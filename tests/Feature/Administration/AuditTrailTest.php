<?php

namespace Tests\Feature\Administration;

use App\Livewire\ClassPilot\Workspace;
use App\Livewire\Support\StudentSupportHub;
use App\Livewire\Users\ManageUserRoles;
use App\Models\AcademicYear;
use App\Models\AuditEvent;
use App\Models\ClassGroup;
use App\Models\ClassPilot\Material;
use App\Models\MyClass;
use App\Models\Result;
use App\Models\School;
use App\Models\Semester;
use App\Models\StudentRecord;
use App\Models\StudentSupportCase;
use App\Models\Subject;
use App\Models\User;
use App\Services\AuditTrail;
use App\Support\ResultPublicationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    public function test_mark_changes_and_result_publication_capture_actor_and_before_after_values(): void
    {
        [$school, $class, $year, $semester] = $this->academicContext('RESULT');
        $teacher = $this->user($school, 'marks-teacher');
        $student = $this->user($school, 'marks-student');
        $record = StudentRecord::query()->create([
            'user_id' => $student->id,
            'my_class_id' => $class->id,
            'admission_number' => 'AUD-'.$student->id,
        ]);
        $subject = Subject::query()->create([
            'school_id' => $school->id,
            'my_class_id' => $class->id,
            'name' => 'Audit Mathematics',
            'short_name' => 'AUD-MTH',
            'is_general' => false,
            'is_legacy' => false,
        ]);
        $record->studentSubjects()->attach($subject->id, ['my_class_id' => $class->id]);
        $this->actingAs($teacher);

        $result = Result::query()->create([
            'student_record_id' => $record->id,
            'subject_id' => $subject->id,
            'academic_year_id' => $year->id,
            'semester_id' => $semester->id,
            'ca1_score' => 10,
            'ca2_score' => 20,
            'exam_score' => 40,
            'approved' => false,
        ]);
        $result->update(['ca1_score' => 15, 'approved' => true]);
        ResultPublicationStatus::setTermPublished($school->id, $year->id, $semester->id, true, $teacher->id);

        $updated = AuditEvent::query()->where('event', 'marks.updated')->firstOrFail();
        $this->assertSame($teacher->id, $updated->actor_id);
        $this->assertSame(10.0, (float) $updated->old_values['ca1_score']);
        $this->assertSame(15.0, (float) $updated->new_values['ca1_score']);
        $this->assertDatabaseHas('audit_events', [
            'school_id' => $school->id,
            'event' => 'results.published',
            'actor_id' => $teacher->id,
        ]);
    }

    public function test_role_changes_ai_approvals_and_support_access_are_recorded(): void
    {
        [$school, $class] = $this->academicContext('ACCOUNTABILITY');
        $principal = $this->user($school, 'principal');
        $principalRole = Role::findOrCreate('principal', 'web');
        $principalRole->givePermissionTo([
            Permission::findOrCreate('manage user roles', 'web'),
            Permission::findOrCreate('access classpilot', 'web'),
            Permission::findOrCreate('approve classpilot material', 'web'),
            Permission::findOrCreate('access student support', 'web'),
            Permission::findOrCreate('manage student support', 'web'),
        ]);
        $principal->assignRole($principalRole);

        $target = $this->user($school, 'target-user');
        $target->assignRole(Role::findOrCreate('student', 'web'));
        Role::findOrCreate('teacher', 'web');
        Livewire::actingAs($principal)->test(ManageUserRoles::class)
            ->set("selectedRoles.{$target->id}", 'teacher')
            ->call('updateUserRole', $target->id);

        $material = Material::query()->create([
            'school_id' => $school->id,
            'author_id' => $principal->id,
            'my_class_id' => $class->id,
            'type' => 'lesson',
            'title' => 'AI-supported audit lesson',
            'topic' => 'Accountability',
            'status' => 'draft',
            'content' => ['title' => 'AI-supported audit lesson'],
            'curriculum_sources' => [],
        ]);
        Livewire::actingAs($principal)->test(Workspace::class)
            ->set('selectedMaterialId', $material->id)
            ->call('approveMaterial');

        $student = $this->user($school, 'support-student');
        $parent = $this->user($school, 'support-parent');
        $case = StudentSupportCase::query()->create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'parent_id' => $parent->id,
            'lead_teacher_id' => $principal->id,
            'opened_by' => $principal->id,
            'source_type' => 'manual',
            'category' => 'wellbeing',
            'priority' => 'high',
            'title' => 'Sensitive support audit case',
            'concern' => 'A sensitive concern requiring a controlled support plan.',
            'goal' => 'Confirm appropriate support and accountability.',
            'status' => 'monitoring',
            'review_on' => now()->addWeek(),
        ]);
        Livewire::actingAs($principal)->test(StudentSupportHub::class)
            ->call('selectCase', $case->id);

        $this->assertDatabaseHas('audit_events', ['event' => 'access.user_role_changed', 'subject_id' => $target->id]);
        $this->assertDatabaseHas('audit_events', ['event' => 'classpilot.material_approved', 'subject_id' => $material->id]);
        $this->assertDatabaseHas('audit_events', ['event' => 'support.case_accessed', 'subject_id' => $case->id]);
    }

    public function test_audit_log_is_tenant_scoped_and_events_are_immutable(): void
    {
        [$schoolA] = $this->academicContext('LOG-A');
        [$schoolB] = $this->academicContext('LOG-B');
        $viewer = $this->user($schoolA, 'audit-viewer');
        $viewer->givePermissionTo(Permission::findOrCreate('view audit log', 'web'));
        $trail = app(AuditTrail::class);
        $eventA = $trail->record($schoolA->id, 'access', 'access.test_a', 'Visible school event.');
        $trail->record($schoolB->id, 'access', 'access.test_b', 'Hidden cross-school event.');

        $this->actingAs($viewer)->get(route('audit-log.index'))
            ->assertOk()
            ->assertSee('Visible school event.')
            ->assertDontSee('Hidden cross-school event.');

        try {
            $eventA->update(['description' => 'Rewritten']);
            $this->fail('Expected audit event mutation to fail.');
        } catch (LogicException $exception) {
            $this->assertSame('Audit events are immutable.', $exception->getMessage());
        }
    }

    private function academicContext(string $suffix): array
    {
        $this->sequence++;
        $school = School::query()->create([
            'name' => "Audit School {$suffix}",
            'code' => "AUD-{$suffix}-{$this->sequence}",
        ]);
        $group = ClassGroup::query()->create(['name' => "Audit Group {$suffix}", 'school_id' => $school->id]);
        $class = MyClass::query()->create(['name' => "Audit Class {$suffix}", 'class_group_id' => $group->id]);
        $year = AcademicYear::query()->create([
            'start_year' => (string) (2040 + $this->sequence),
            'stop_year' => (string) (2041 + $this->sequence),
            'school_id' => $school->id,
        ]);
        $semester = Semester::query()->create([
            'name' => "Audit Term {$suffix}",
            'academic_year_id' => $year->id,
            'school_id' => $school->id,
        ]);
        $school->update(['academic_year_id' => $year->id, 'semester_id' => $semester->id]);

        return [$school, $class, $year, $semester];
    }

    private function user(School $school, string $label): User
    {
        $this->sequence++;

        return User::query()->forceCreate([
            'name' => str($label)->replace('-', ' ')->title(),
            'email' => "{$label}-{$this->sequence}@example.test",
            'email_verified_at' => now(),
            'password' => bcrypt('Password1!'),
            'school_id' => $school->id,
            'locked' => false,
            'requires_password_change' => false,
        ]);
    }
}
