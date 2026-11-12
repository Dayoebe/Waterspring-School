<?php

namespace Tests\Feature\Syllabi;

use App\Livewire\Syllabi\ManageSyllabi;
use App\Models\AcademicYear;
use App\Models\ClassGroup;
use App\Models\MyClass;
use App\Models\School;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\User;
use App\Services\ClassPilot\Contracts\AiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SyllabusAiDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_runs_only_when_clicked_fills_blank_fields_and_never_saves(): void
    {
        $data = $this->curriculum();
        $provider = new FakeSyllabusProvider;
        app()->instance(AiProvider::class, $provider);

        $component = Livewire::actingAs($data['user'])
            ->test(ManageSyllabi::class)
            ->call('switchMode', 'create')
            ->set('class', (string) $data['class']->id)
            ->set('subject', (string) $data['subject']->id)
            ->set('name', 'Fractions')
            ->set('description', 'Keep this teacher-written description.');

        $this->assertSame([], $provider->calls);
        $this->assertDatabaseCount('syllabi', 0);

        $component->call('generateSyllabusWithAi')
            ->assertHasNoErrors()
            ->assertSet('name', 'Fractions')
            ->assertSet('description', 'Keep this teacher-written description.')
            ->assertSet('overview', 'Explore fractions as equal parts and useful quantities.')
            ->assertSet('learningOutcomes', 'Identify, compare, and apply simple fractions.')
            ->assertSee('AI filled the blank syllabus fields');

        $this->assertCount(1, $provider->calls);
        $this->assertStringContainsString('"title_or_topic":"Fractions"', $provider->calls[0]['prompt']);
        $this->assertStringContainsString('Keep this teacher-written description.', $provider->calls[0]['prompt']);
        $this->assertDatabaseCount('syllabi', 0);
        $this->assertDatabaseHas('class_pilot_usage', [
            'user_id' => $data['user']->id,
            'feature' => 'syllabus',
            'status' => 'completed',
        ]);
    }

    public function test_invalid_ai_output_is_rejected_without_changing_the_draft(): void
    {
        $data = $this->curriculum();
        $provider = new FakeSyllabusProvider;
        $provider->malformed = true;
        app()->instance(AiProvider::class, $provider);

        Livewire::actingAs($data['user'])
            ->test(ManageSyllabi::class)
            ->call('switchMode', 'create')
            ->set('class', (string) $data['class']->id)
            ->set('subject', (string) $data['subject']->id)
            ->set('name', 'Fractions')
            ->call('generateSyllabusWithAi')
            ->assertHasErrors('aiGeneration')
            ->assertSet('description', '')
            ->assertSet('overview', '');

        $this->assertDatabaseCount('syllabi', 0);
        $this->assertDatabaseHas('class_pilot_usage', [
            'user_id' => $data['user']->id,
            'feature' => 'syllabus',
            'status' => 'failed',
        ]);
    }

    public function test_user_without_create_permission_cannot_trigger_syllabus_ai(): void
    {
        $data = $this->curriculum(grantPermission: false);
        app()->instance(AiProvider::class, new FakeSyllabusProvider);

        Livewire::actingAs($data['user'])
            ->test(ManageSyllabi::class)
            ->set('class', (string) $data['class']->id)
            ->set('subject', (string) $data['subject']->id)
            ->set('name', 'Fractions')
            ->call('generateSyllabusWithAi')
            ->assertForbidden();
    }

    private function curriculum(bool $grantPermission = true): array
    {
        $school = School::query()->create(['name' => 'Syllabus School', 'code' => 'SYL']);
        $year = AcademicYear::query()->create([
            'start_year' => '2030',
            'stop_year' => '2031',
            'school_id' => $school->id,
        ]);
        $term = Semester::query()->create([
            'name' => 'First Term',
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
        ]);
        $school->update(['academic_year_id' => $year->id, 'semester_id' => $term->id]);
        $group = ClassGroup::query()->create(['name' => 'Junior', 'school_id' => $school->id]);
        $class = MyClass::query()->create(['name' => 'Year 7', 'class_group_id' => $group->id]);
        $subject = Subject::query()->create([
            'name' => 'Mathematics',
            'short_name' => 'MATH',
            'school_id' => $school->id,
            'is_general' => false,
            'is_legacy' => false,
        ]);
        $subject->classes()->attach($class->id, ['school_id' => $school->id]);
        $user = User::query()->forceCreate([
            'name' => 'Curriculum Lead',
            'email' => 'curriculum@example.test',
            'email_verified_at' => now(),
            'password' => bcrypt('Password1!'),
            'school_id' => $school->id,
            'locked' => false,
        ]);
        $user->assignRole(Role::findOrCreate('principal', 'web'));
        if ($grantPermission) {
            $user->givePermissionTo(Permission::findOrCreate('create syllabus', 'web'));
        }

        return compact('school', 'year', 'term', 'class', 'subject', 'user');
    }
}

class FakeSyllabusProvider implements AiProvider
{
    public array $calls = [];

    public bool $malformed = false;

    public function generate(string $system, string $prompt): array
    {
        $this->calls[] = compact('system', 'prompt');

        if ($this->malformed) {
            return ['description' => 'Incomplete response'];
        }

        return [
            'description' => 'An age-appropriate introduction to fractions.',
            'overview' => 'Explore fractions as equal parts and useful quantities.',
            'aims' => 'Develop conceptual understanding and confident application.',
            'learning_outcomes' => 'Identify, compare, and apply simple fractions.',
            'scope_and_sequence' => "Weeks 1-3: Meaning and representation\nWeeks 4-8: Comparison and application",
            'prerequisites' => 'Whole-number counting and equal sharing.',
            'teaching_methodology' => 'Concrete materials, modelling, discussion, and guided practice.',
            'assessment_plan' => 'Entry task, weekly checks, practical work, and an end-of-term task.',
            'learning_resources' => 'Fraction strips, paper shapes, and teacher-created examples.',
            'differentiation' => 'Visual scaffolds, peer support, and extension problems.',
            'cross_curricular_links' => 'Measurement, food, art, and everyday sharing.',
            'homework_policy' => 'Short purposeful practice followed by feedback.',
            'references' => '',
        ];
    }

    public function name(): string
    {
        return 'fake';
    }

    public function model(): string
    {
        return 'syllabus-test-model';
    }

    public function configured(): bool
    {
        return true;
    }
}
