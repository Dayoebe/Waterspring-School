<?php

namespace Tests\Feature\Schemes;

use App\Livewire\Schemes\SchemeOfWorkHub;
use App\Models\AcademicYear;
use App\Models\ClassGroup;
use App\Models\MyClass;
use App\Models\SchemeOfWork;
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

class SchemeAiDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_fills_blank_scheme_fields_only_when_clicked_and_never_saves(): void
    {
        $data = $this->curriculum();
        $provider = new FakeSchemeProvider;
        app()->instance(AiProvider::class, $provider);

        $component = Livewire::actingAs($data['user'])
            ->test(SchemeOfWorkHub::class)
            ->call('createScheme')
            ->set('type', 'subject')
            ->set('classId', $data['class']->id)
            ->set('subjectId', $data['subject']->id)
            ->set('title', 'Mathematics First Term Scheme')
            ->set('aim', 'Keep this teacher-written aim.');

        $this->assertSame([], $provider->calls);
        $this->assertDatabaseCount('schemes_of_work', 1);

        $component->call('generateSchemeWithAi')
            ->assertHasNoErrors()
            ->assertSet('title', 'Mathematics First Term Scheme')
            ->assertSet('aim', 'Keep this teacher-written aim.')
            ->assertSet('objectives', 'Explain concepts and apply them confidently.')
            ->assertSet('teachingApproach', 'Model, discuss, practise, investigate, and reflect.')
            ->assertSee('AI filled the blank scheme fields');

        $this->assertCount(1, $provider->calls);
        $this->assertStringContainsString('The requested feature is scheme.', $provider->calls[0]['system']);
        $this->assertStringContainsString('Keep this teacher-written aim.', $provider->calls[0]['prompt']);
        $this->assertDatabaseCount('schemes_of_work', 1);
        $this->assertDatabaseHas('class_pilot_usage', [
            'user_id' => $data['user']->id,
            'feature' => 'scheme',
            'status' => 'completed',
        ]);
    }

    public function test_ai_fills_blank_weekly_topic_fields_without_completion_notes_or_saving(): void
    {
        $data = $this->curriculum();
        $provider = new FakeSchemeProvider;
        app()->instance(AiProvider::class, $provider);

        Livewire::actingAs($data['user'])
            ->test(SchemeOfWorkHub::class)
            ->call('createTopic')
            ->set('topic', 'Fractions as equal parts')
            ->set('contentOutline', 'Keep this teacher-written outline.')
            ->set('completionNotes', 'Teacher observation stays untouched.')
            ->call('generateTopicWithAi')
            ->assertHasNoErrors()
            ->assertSet('topic', 'Fractions as equal parts')
            ->assertSet('topicAim', 'Understand fractions as equal parts of a whole.')
            ->assertSet('contentOutline', 'Keep this teacher-written outline.')
            ->assertSet('completionNotes', 'Teacher observation stays untouched.')
            ->assertSet('assessment', 'Label and compare two fraction models.')
            ->assertSee('AI filled the blank weekly-topic fields');

        $this->assertCount(1, $provider->calls);
        $this->assertStringContainsString('The requested feature is scheme_topic.', $provider->calls[0]['system']);
        $this->assertStringContainsString('"topic":"Fractions as equal parts"', $provider->calls[0]['prompt']);
        $this->assertDatabaseCount('scheme_topics', 0);
        $this->assertDatabaseHas('class_pilot_usage', [
            'user_id' => $data['user']->id,
            'feature' => 'scheme_topic',
            'status' => 'completed',
        ]);
    }

    public function test_malformed_topic_output_is_rejected_without_changing_the_draft(): void
    {
        $data = $this->curriculum();
        $provider = new FakeSchemeProvider;
        $provider->malformedTopic = true;
        app()->instance(AiProvider::class, $provider);

        Livewire::actingAs($data['user'])
            ->test(SchemeOfWorkHub::class)
            ->call('createTopic')
            ->set('topic', 'Fractions')
            ->call('generateTopicWithAi')
            ->assertHasErrors('topicAiGeneration')
            ->assertSet('topicAim', '')
            ->assertSet('contentOutline', '');

        $this->assertDatabaseCount('scheme_topics', 0);
    }

    public function test_user_without_management_permission_cannot_trigger_scheme_ai(): void
    {
        $data = $this->curriculum(grantManagement: false);
        app()->instance(AiProvider::class, new FakeSchemeProvider);

        Livewire::actingAs($data['user'])
            ->test(SchemeOfWorkHub::class)
            ->set('selectedSchemeId', $data['scheme']->id)
            ->set('topic', 'Fractions')
            ->call('generateTopicWithAi')
            ->assertForbidden();
    }

    private function curriculum(bool $grantManagement = true): array
    {
        $school = School::query()->create(['name' => 'Scheme School', 'code' => 'SCH']);
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
            'email' => 'scheme-author@example.test',
            'email_verified_at' => now(),
            'password' => bcrypt('Password1!'),
            'school_id' => $school->id,
            'locked' => false,
        ]);
        $user->assignRole(Role::findOrCreate('principal', 'web'));
        $user->givePermissionTo(Permission::findOrCreate('view schemes of work', 'web'));
        if ($grantManagement) {
            $user->givePermissionTo(Permission::findOrCreate('manage schemes of work', 'web'));
        }
        $scheme = SchemeOfWork::query()->create([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'semester_id' => $term->id,
            'my_class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $user->id,
            'type' => 'subject',
            'scope_key' => 'subject:'.$subject->id,
            'title' => 'Existing Mathematics Scheme',
            'aim' => 'Develop mathematical understanding.',
            'objectives' => 'Explain and apply foundational concepts.',
            'published_at' => now(),
            'updated_by' => $user->id,
        ]);

        return compact('school', 'year', 'term', 'class', 'subject', 'user', 'scheme');
    }
}

class FakeSchemeProvider implements AiProvider
{
    public array $calls = [];

    public bool $malformedTopic = false;

    public function generate(string $system, string $prompt): array
    {
        $this->calls[] = compact('system', 'prompt');

        if (str_contains($system, 'scheme_topic')) {
            if ($this->malformedTopic) {
                return ['aim' => 'Incomplete response'];
            }

            return [
                'aim' => 'Understand fractions as equal parts of a whole.',
                'objectives' => 'Identify, represent, and compare simple fractions.',
                'content_outline' => 'Meaning, representation, and comparison of fractions.',
                'teacher_activities' => 'Model equal sharing with concrete materials.',
                'learner_activities' => 'Build, label, and compare fraction models.',
                'resources' => 'Paper shapes, counters, and fraction strips.',
                'assessment' => 'Label and compare two fraction models.',
                'homework' => 'Find and describe three examples of fractions at home.',
            ];
        }

        return [
            'aim' => 'Build secure understanding across the term.',
            'objectives' => 'Explain concepts and apply them confidently.',
            'teaching_approach' => 'Model, discuss, practise, investigate, and reflect.',
            'assessment_strategy' => 'Use entry tasks, observation, practice, and an end-of-term task.',
            'resources' => 'Teacher-created examples, manipulatives, and local materials.',
            'learner_support' => 'Use scaffolds, flexible grouping, and extension challenges.',
        ];
    }

    public function name(): string
    {
        return 'fake';
    }

    public function model(): string
    {
        return 'scheme-test-model';
    }

    public function configured(): bool
    {
        return true;
    }
}
