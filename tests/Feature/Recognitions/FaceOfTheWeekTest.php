<?php

namespace Tests\Feature\Recognitions;

use App\Livewire\Recognitions\ManageRecognitions;
use App\Models\AcademicYear;
use App\Models\ClassGroup;
use App\Models\MyClass;
use App\Models\School;
use App\Models\Semester;
use App\Models\StudentRecognition;
use App\Models\StudentRecord;
use App\Models\User;
use App\Services\ClassPilot\Contracts\AiProvider;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FaceOfTheWeekTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_page_only_displays_published_records_with_public_approval(): void
    {
        $context = $this->context();
        $this->recognition($context, 'Published Student', 'published', true);
        $this->recognition($context, 'Private Student', 'published', false);
        $this->recognition($context, 'Draft Student', 'draft', true);

        $this->get(route('face-of-the-week'))
            ->assertOk()
            ->assertSee('Published Student')
            ->assertDontSee('Private Student')
            ->assertDontSee('Draft Student');
    }

    public function test_database_prevents_two_published_selections_for_one_class_and_week(): void
    {
        $context = $this->context();
        $this->recognition($context, 'First Winner', 'published', true);

        $this->expectException(QueryException::class);
        $this->recognition($context, 'Second Winner', 'published', true);
    }

    public function test_class_teacher_can_publish_for_assigned_class_but_cannot_manage_another_class(): void
    {
        $context = $this->context();
        $otherClass = MyClass::query()->create(['name' => 'Other Class', 'class_group_id' => $context['group']->id]);
        $teacher = $this->user($context['school'], 'Assigned Teacher');
        $teacher->assignRole(Role::findOrCreate('teacher', 'web'));
        foreach (['read student recognition', 'create student recognition', 'update student recognition', 'publish student recognition'] as $permission) {
            $teacher->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $context['class']->teachers()->attach($teacher->id);

        Livewire::actingAs($teacher)
            ->test(ManageRecognitions::class)
            ->call('openCreate')
            ->assertSee($context['class']->name)
            ->assertDontSee($otherClass->name)
            ->set('classId', (string) $context['class']->id)
            ->set('studentId', (string) $context['student']->id)
            ->assertSee('Student profile photograph')
            ->assertSee($context['student']->profile_photo_url, false)
            ->set('category', 'character_integrity')
            ->set('reason', 'This student consistently acts with honesty, kindness and respect for classmates.')
            ->set('message', 'Congratulations on setting a thoughtful example for your class.')
            ->set('weekDate', now('Africa/Lagos')->toDateString())
            ->set('publicDisplayApproved', true)
            ->call('publish')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('student_recognitions', [
            'student_id' => $context['student']->id,
            'my_class_id' => $context['class']->id,
            'academic_year_id' => $context['year']->id,
            'semester_id' => $context['term']->id,
            'status' => 'published',
            'published_slot' => 1,
            'published_by' => $teacher->id,
        ]);
    }

    public function test_ai_drafts_both_blank_fields_without_overwriting_or_saving(): void
    {
        $context = $this->context();
        $teacher = $this->user($context['school'], 'Drafting Teacher');
        $teacher->assignRole(Role::findOrCreate('teacher', 'web'));
        foreach (['read student recognition', 'create student recognition'] as $permission) {
            $teacher->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $context['class']->teachers()->attach($teacher->id);
        $provider = new FakeRecognitionProvider;
        app()->instance(AiProvider::class, $provider);

        $component = Livewire::actingAs($teacher)
            ->test(ManageRecognitions::class)
            ->call('openCreate')
            ->assertSee('Active academic context')
            ->assertSee($context['year']->name)
            ->assertSee($context['term']->name)
            ->assertDontSee('Select session')
            ->set('classId', (string) $context['class']->id)
            ->set('studentId', (string) $context['student']->id)
            ->set('category', 'character_integrity')
            ->call('generateReasonWithAi')
            ->assertHasNoErrors()
            ->assertSet('reason', 'Recognition Student demonstrates integrity through respectful and responsible choices, providing a positive example for the class community.')
            ->call('generateMessageWithAi')
            ->assertHasNoErrors()
            ->assertSet('message', 'Congratulations, Recognition Student! Your integrity and positive example in Primary One are worthy of recognition. Keep making thoughtful choices and encouraging others.')
            ->assertSee('never saved or published');

        $this->assertCount(2, $provider->calls);
        $this->assertDatabaseCount('student_recognitions', 0);
        $this->assertDatabaseHas('class_pilot_usage', [
            'user_id' => $teacher->id,
            'feature' => 'student_recognition_reason',
            'status' => 'completed',
        ]);

        $component->set('reason', 'Teacher-written verified reason that must remain unchanged.')
            ->call('generateReasonWithAi')
            ->assertHasErrors('reasonAiGeneration')
            ->assertSet('reason', 'Teacher-written verified reason that must remain unchanged.');

        $readOnlyTeacher = $this->user($context['school'], 'Read Only Teacher');
        $readOnlyTeacher->assignRole(Role::findOrCreate('teacher', 'web'));
        $readOnlyTeacher->givePermissionTo(Permission::findOrCreate('read student recognition', 'web'));
        $context['class']->teachers()->attach($readOnlyTeacher->id);
        Livewire::actingAs($readOnlyTeacher)
            ->test(ManageRecognitions::class)
            ->set('classId', (string) $context['class']->id)
            ->set('studentId', (string) $context['student']->id)
            ->set('category', 'character_integrity')
            ->call('generateReasonWithAi')
            ->assertForbidden();

        $this->assertCount(2, $provider->calls);
    }

    private function context(): array
    {
        Role::findOrCreate('student', 'web');
        $school = School::query()->create(['name' => 'Recognition School', 'code' => 'REC'.uniqid()]);
        $year = AcademicYear::query()->create(['start_year' => '2030', 'stop_year' => '2031', 'school_id' => $school->id]);
        $term = Semester::query()->create(['name' => 'First Term', 'school_id' => $school->id, 'academic_year_id' => $year->id]);
        $school->update(['academic_year_id' => $year->id, 'semester_id' => $term->id]);
        $group = ClassGroup::query()->create(['name' => 'Primary', 'school_id' => $school->id]);
        $class = MyClass::query()->create(['name' => 'Primary One', 'class_group_id' => $group->id]);
        $student = $this->user($school, 'Recognition Student');
        $student->update(['profile_photo_path' => 'students/recognition-student.jpg']);
        $student->assignRole('student');
        $record = StudentRecord::query()->create([
            'user_id' => $student->id,
            'my_class_id' => $class->id,
            'admission_number' => 'REC-001',
            'admission_date' => now()->toDateString(),
            'is_graduated' => false,
        ]);
        DB::table('academic_year_student_record')->insert([
            'academic_year_id' => $year->id,
            'student_record_id' => $record->id,
            'my_class_id' => $class->id,
            'section_id' => null,
        ]);
        $creator = $this->user($school, 'Recognition Administrator');

        return compact('school', 'year', 'term', 'group', 'class', 'student', 'record', 'creator');
    }

    private function recognition(array $context, string $studentName, string $status, bool $public): StudentRecognition
    {
        $student = $studentName === 'Recognition Student' ? $context['student'] : $this->user($context['school'], $studentName);
        $start = Carbon::now('Africa/Lagos')->startOfWeek(Carbon::MONDAY);
        if ($studentName === 'Private Student') {
            $start->subWeek();
        }

        return StudentRecognition::query()->create([
            'school_id' => $context['school']->id,
            'student_id' => $student->id,
            'my_class_id' => $context['class']->id,
            'academic_year_id' => $context['year']->id,
            'semester_id' => $context['term']->id,
            'category' => 'character_integrity',
            'reason' => 'A specific record of excellent conduct and a positive contribution to the class.',
            'congratulatory_message' => 'Congratulations on this well-earned recognition.',
            'week_start_date' => $start,
            'week_end_date' => $start->copy()->endOfWeek(Carbon::SUNDAY),
            'status' => $status,
            'public_display_approved' => $public,
            'published_slot' => $status === 'published' ? 1 : null,
            'published_at' => $status === 'published' ? now() : null,
            'created_by' => $context['creator']->id,
            'published_by' => $status === 'published' ? $context['creator']->id : null,
        ]);
    }

    private function user(School $school, string $name): User
    {
        return User::query()->forceCreate([
            'name' => $name,
            'email' => str($name)->slug().'-'.uniqid().'@example.test',
            'password' => bcrypt('Password1!'),
            'school_id' => $school->id,
            'locked' => false,
        ]);
    }
}

class FakeRecognitionProvider implements AiProvider
{
    public array $calls = [];

    public function generate(string $system, string $prompt): array
    {
        $this->calls[] = compact('system', 'prompt');

        return str_contains($system, 'student_recognition_reason')
            ? ['reason' => 'Recognition Student demonstrates integrity through respectful and responsible choices, providing a positive example for the class community.']
            : ['message' => 'Congratulations, Recognition Student! Your integrity and positive example in Primary One are worthy of recognition. Keep making thoughtful choices and encouraging others.'];
    }

    public function name(): string
    {
        return 'fake';
    }

    public function model(): string
    {
        return 'recognition-test-model';
    }

    public function configured(): bool
    {
        return true;
    }
}
