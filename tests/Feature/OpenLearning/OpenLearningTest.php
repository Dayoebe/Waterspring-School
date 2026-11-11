<?php

namespace Tests\Feature\OpenLearning;

use App\Livewire\OpenLearning\Workspace;
use App\Models\AcademicYear;
use App\Models\ClassGroup;
use App\Models\MyClass;
use App\Models\OpenLearningRecord;
use App\Models\SchemeOfWork;
use App\Models\SchemeTopic;
use App\Models\School;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\User;
use App\Services\ClassPilot\Contracts\AiProvider;
use App\Services\OpenLearning\AssessmentMarker;
use App\Services\OpenLearning\CurriculumCatalog;
use App\Services\OpenLearning\SessionStore;
use App\Services\OpenLearning\Tutor;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OpenLearningTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_discover_only_published_assigned_curriculum_without_registration(): void
    {
        $published = $this->curriculum();
        $draft = SchemeOfWork::query()->create([
            'school_id' => $published['school']->id,
            'academic_year_id' => $published['year']->id,
            'semester_id' => $published['term']->id,
            'my_class_id' => $published['class']->id,
            'subject_id' => $published['subject']->id,
            'teacher_id' => $published['teacher']->id,
            'type' => 'subject',
            'scope_key' => 'draft-subject',
            'title' => 'Draft scheme',
            'aim' => 'Draft aim',
            'objectives' => 'Draft objective',
            'published_at' => null,
        ]);
        SchemeTopic::query()->create([
            'scheme_of_work_id' => $draft->id,
            'week_number' => 2,
            'topic' => 'Secret draft topic',
            'aim' => 'Draft',
            'objectives' => 'Draft',
        ]);

        $this->get(route('open-learning'))
            ->assertOk()
            ->assertSee('Open Learning')
            ->assertSee($published['class']->name)
            ->assertDontSee('Secret draft topic')
            ->assertSee('Learn without an account')
            ->assertDontSee('Create an Open Learning account');

        $catalog = app(CurriculumCatalog::class);
        $this->assertCount(1, $catalog->topics(
            $published['school'],
            $published['class']->id,
            $published['subject']->id,
            $published['term']->id,
        ));
        $this->assertNull($catalog->selection(
            $published['school'],
            $published['class']->id,
            $published['subject']->id,
            $published['term']->id,
            $draft->topics()->first()->id,
        ));
    }

    public function test_guest_can_start_a_progressive_lesson_change_style_and_keep_context(): void
    {
        $data = $this->curriculum();
        $provider = $this->fakeProvider();

        Livewire::test(Workspace::class)
            ->set('classId', (string) $data['class']->id)
            ->set('subjectId', (string) $data['subject']->id)
            ->set('semesterId', (string) $data['term']->id)
            ->set('topicId', (string) $data['topic']->id)
            ->call('startLesson')
            ->assertSee('Understanding fractions')
            ->assertSee('A fraction describes equal parts')
            ->call('saveProgress')
            ->assertSet('showSavePrompt', true)
            ->assertSee('To save your learning progress permanently')
            ->set('showSavePrompt', false)
            ->call('changeStyle', 'detailed')
            ->call('quickAction', 'deeper')
            ->assertSee('A denominator tells us how many equal parts form the whole')
            ->call('exploreBeyond')
            ->assertSee('Fractions help us describe fair shares and measurements')
            ->assertSee('Engineering measurements');

        $state = app(SessionStore::class)->current();
        $this->assertSame('detailed', $state['teaching_style']);
        $this->assertCount(2, $state['messages']);
        $this->assertStringContainsString('Use thorough explanations', collect($provider->calls)->last()['system']);
    }

    public function test_assessment_answers_stay_hidden_until_server_side_marking_and_duplicate_submission_is_rejected(): void
    {
        $data = $this->curriculum();
        $this->fakeProvider();

        $component = Livewire::test(Workspace::class)
            ->set('classId', (string) $data['class']->id)
            ->set('subjectId', (string) $data['subject']->id)
            ->set('semesterId', (string) $data['term']->id)
            ->set('topicId', (string) $data['topic']->id)
            ->call('startLesson')
            ->call('prepareAssessment')
            ->assertDontSee('four-secret-answer')
            ->assertDontSee('half-secret-answer');

        $state = app(SessionStore::class)->current();
        $questions = collect($state['assessment']['questions']);
        $component
            ->set('answers.'.$questions[0]['id'], '4')
            ->set('answers.'.$questions[1]['id'], 'True')
            ->set('answers.'.$questions[2]['id'], '1/2')
            ->call('submitAssessment', $state['assessment']['submission_token'])
            ->assertSee('100%')
            ->assertSee('four-secret-answer')
            ->assertSee('half-secret-answer');

        $this->assertNotNull(data_get(app(SessionStore::class)->current(), 'assessment.submitted_at'));

        $component->call('submitAssessment', $state['assessment']['submission_token'])->assertStatus(409);
    }

    public function test_objective_marking_is_deterministic_and_does_not_call_ai_for_scores(): void
    {
        $data = $this->curriculum();
        $provider = $this->fakeProvider();
        $store = app(SessionStore::class);
        $state = $store->start([
            'school_id' => $data['school']->id,
            'academic_year_id' => $data['year']->id,
            'class_id' => $data['class']->id,
            'subject_id' => $data['subject']->id,
            'semester_id' => $data['term']->id,
            'topic_id' => $data['topic']->id,
        ], 'beginner');
        $assessment = app(Tutor::class)->assessment($state, $data['topic'], 'easy');
        $before = count($provider->calls);
        $answers = collect($assessment['questions'])->mapWithKeys(fn ($question) => [
            $question['id'] => $question['expected_answer'],
        ])->all();

        $result = app(AssessmentMarker::class)->mark($state, $data['topic'], $assessment, $answers);

        $this->assertSame(100, $result['percentage']);
        $this->assertSame($before, count($provider->calls));
    }

    public function test_only_eligible_owner_can_save_and_restore_a_learning_record(): void
    {
        $data = $this->curriculum();
        $this->fakeProvider();
        $student = $this->user($data['school'], 'learner@example.test', 'student');

        $component = Livewire::actingAs($student)->test(Workspace::class)
            ->set('classId', (string) $data['class']->id)
            ->set('subjectId', (string) $data['subject']->id)
            ->set('semesterId', (string) $data['term']->id)
            ->set('topicId', (string) $data['topic']->id)
            ->call('startLesson')
            ->call('saveProgress')
            ->assertSee('saved to your Water Spring account');

        $record = OpenLearningRecord::query()->sole();
        $this->assertSame($student->id, $record->user_id);
        $component->call('loadRecord', $record->id)->assertSee('Saved learning progress restored');

        $other = $this->user($data['school'], 'other@example.test', 'student');
        $this->expectException(ModelNotFoundException::class);
        Livewire::actingAs($other)->test(Workspace::class)
            ->call('loadRecord', $record->id);
    }

    public function test_ai_failure_and_rate_limit_preserve_completed_session_state(): void
    {
        $data = $this->curriculum();
        $this->fakeProvider();
        config()->set('open-learning.requests_per_minute', 1);

        $component = Livewire::test(Workspace::class)
            ->set('classId', (string) $data['class']->id)
            ->set('subjectId', (string) $data['subject']->id)
            ->set('semesterId', (string) $data['term']->id)
            ->set('topicId', (string) $data['topic']->id)
            ->call('startLesson');
        $section = data_get(app(SessionStore::class)->current(), 'sections.1');

        $component->call('quickAction', 'example')->assertSee('learning a little too quickly');

        $this->assertSame($section, data_get(app(SessionStore::class)->current(), 'sections.1'));
    }

    public function test_malformed_ai_response_shows_a_safe_error_and_current_section_can_be_retried(): void
    {
        $data = $this->curriculum();
        $provider = $this->fakeProvider();
        $provider->malformedLesson = true;

        $component = Livewire::test(Workspace::class)
            ->set('classId', (string) $data['class']->id)
            ->set('subjectId', (string) $data['subject']->id)
            ->set('semesterId', (string) $data['term']->id)
            ->set('topicId', (string) $data['topic']->id)
            ->call('startLesson')
            ->assertSee('incomplete response')
            ->assertSee('Retry this section');

        $this->assertEmpty(data_get(app(SessionStore::class)->current(), 'sections', []));
        $provider->malformedLesson = false;

        $component->call('retryCurrentSection')
            ->assertSee('A fraction describes equal parts');
    }

    private function curriculum(): array
    {
        $school = School::query()->create(['name' => 'Watersprings International College', 'code' => 'WSIC']);
        $year = AcademicYear::query()->create(['school_id' => $school->id, 'start_year' => 2026, 'stop_year' => 2027]);
        $term = Semester::query()->create(['school_id' => $school->id, 'academic_year_id' => $year->id, 'name' => 'Second Term']);
        $school->update(['academic_year_id' => $year->id, 'semester_id' => $term->id]);
        config()->set('open-learning.school_id', $school->id);
        config()->set('class-pilot.enabled', true);

        $group = ClassGroup::query()->create(['school_id' => $school->id, 'name' => 'College']);
        $class = MyClass::query()->create(['class_group_id' => $group->id, 'name' => 'Year 8']);
        $subject = Subject::query()->create([
            'school_id' => $school->id,
            'name' => 'Mathematics',
            'short_name' => 'MATH',
            'is_general' => false,
            'is_legacy' => false,
        ]);
        DB::table('class_subject')->insert([
            'school_id' => $school->id,
            'my_class_id' => $class->id,
            'subject_id' => $subject->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $teacher = $this->user($school, 'teacher@example.test', 'teacher');
        $scheme = SchemeOfWork::query()->create([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'semester_id' => $term->id,
            'my_class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'type' => 'subject',
            'scope_key' => 'subject-'.$subject->id,
            'title' => 'Year 8 Mathematics Scheme',
            'aim' => 'Understand and apply fractions.',
            'objectives' => 'Recognise, compare, and calculate with fractions.',
            'resources' => 'Fraction strips and measuring cups.',
            'published_at' => now(),
        ]);
        $topic = SchemeTopic::query()->create([
            'scheme_of_work_id' => $scheme->id,
            'week_number' => 1,
            'topic' => 'Fractions',
            'aim' => 'Understand equal parts of a whole.',
            'objectives' => 'Identify numerators and denominators; compare simple fractions.',
            'content_outline' => 'Meaning of fractions, equivalent fractions, and everyday uses.',
            'assessment' => 'Solve short fraction questions.',
        ]);

        return compact('school', 'year', 'term', 'group', 'class', 'subject', 'teacher', 'scheme', 'topic');
    }

    private function user(School $school, string $email, string $role): User
    {
        $user = User::query()->forceCreate([
            'name' => strtok($email, '@'),
            'email' => $email,
            'email_verified_at' => now(),
            'password' => bcrypt('Password1!'),
            'school_id' => $school->id,
            'locked' => false,
        ]);
        $user->assignRole(Role::findOrCreate($role, 'web'));

        return $user;
    }

    private function fakeProvider(): FakeOpenLearningProvider
    {
        $provider = new FakeOpenLearningProvider;
        app()->instance(AiProvider::class, $provider);

        return $provider;
    }
}

class FakeOpenLearningProvider implements AiProvider
{
    public array $calls = [];

    public bool $malformedLesson = false;

    public function name(): string
    {
        return 'groq';
    }

    public function model(): string
    {
        return 'fake-learning-model';
    }

    public function configured(): bool
    {
        return true;
    }

    public function generate(string $system, string $prompt): array
    {
        $this->calls[] = compact('system', 'prompt');

        if (str_contains($system, 'open_lesson_section')) {
            if ($this->malformedLesson) {
                return ['lesson_title' => 'Incomplete'];
            }

            return [
                'lesson_title' => 'Understanding fractions',
                'objectives' => ['Identify equal parts', 'Compare simple fractions'],
                'section_plan' => ['Meet fractions', 'Equivalent fractions', 'Apply and review'],
                'section' => [
                    'heading' => 'Meet fractions',
                    'content' => ['A fraction describes equal parts of one whole.'],
                    'key_points' => ['The numerator counts selected parts.', 'The denominator counts all equal parts.'],
                    'examples' => ['One of four equal orange slices is one quarter.'],
                    'check_question' => 'What fraction is one part out of four equal parts?',
                    'summary' => 'Fractions name equal parts of a whole.',
                ],
            ];
        }

        if (str_contains($system, 'open_companion')) {
            return [
                'answer' => 'A denominator tells us how many equal parts form the whole.',
                'key_points' => ['Equal parts matter'],
                'example' => 'A cake cut into eight equal slices has denominator eight.',
                'check_for_understanding' => 'What is the denominator in three fifths?',
                'suggested_actions' => ['Try an example'],
            ];
        }

        if (str_contains($system, 'open_assessment')) {
            return [
                'title' => 'Fractions check',
                'instructions' => 'Answer every question.',
                'questions' => [
                    ['type' => 'multiple_choice', 'prompt' => 'What is 2 + 2?', 'options' => ['3', '4', '5'], 'expected_answer' => '4', 'accepted_answers' => ['4'], 'marking_guide' => 'One mark for 4.', 'explanation' => 'Two plus two is four-secret-answer.', 'marks' => 1],
                    ['type' => 'true_false', 'prompt' => 'One half equals two quarters.', 'options' => ['True', 'False'], 'expected_answer' => 'True', 'accepted_answers' => ['true'], 'marking_guide' => 'One mark for true.', 'explanation' => 'They are equivalent fractions.', 'marks' => 1],
                    ['type' => 'fill_gap', 'prompt' => 'One of two equal parts is ____.', 'options' => [], 'expected_answer' => '1/2', 'accepted_answers' => ['one half'], 'marking_guide' => 'One mark for one half.', 'explanation' => 'One divided by two is half-secret-answer.', 'marks' => 1],
                ],
            ];
        }

        if (str_contains($system, 'open_beyond')) {
            return [
                'introduction' => 'Fractions help us describe fair shares and measurements.',
                'everyday_examples' => ['Sharing food equally'],
                'real_world_applications' => ['Measuring ingredients'],
                'industry_applications' => ['Engineering measurements'],
                'careers' => ['Chef'],
                'practical_scenarios' => ['Scale a recipe'],
                'interesting_discoveries' => ['Equivalent fractions name the same quantity'],
                'subject_connections' => ['Science measurements'],
            ];
        }

        return ['evaluations' => []];
    }
}
