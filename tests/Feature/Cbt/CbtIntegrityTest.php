<?php

namespace Tests\Feature\Cbt;

use App\Livewire\Cbt\CbtExamInterface;
use App\Models\AcademicYear;
use App\Models\Assessment\Assessment;
use App\Models\Assessment\AttemptSession;
use App\Models\Assessment\Question;
use App\Models\Assessment\StudentAnswer;
use App\Models\ClassGroup;
use App\Models\MyClass;
use App\Models\School;
use App\Models\Semester;
use App\Models\StudentRecord;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use ReflectionMethod;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CbtIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_payload_excludes_marking_keys_and_explanations(): void
    {
        [$student, $assessment] = $this->examContext();

        $component = Livewire::actingAs($student)
            ->test(CbtExamInterface::class, ['assessment' => $assessment->id]);

        $questions = $component->get('questions');

        $this->assertCount(1, $questions);
        $this->assertArrayNotHasKey('correct_answers', $questions[0]);
        $this->assertArrayNotHasKey('original_correct_answers', $questions[0]);
        $this->assertArrayNotHasKey('display_to_original_map', $questions[0]);
        $this->assertArrayNotHasKey('explanation', $questions[0]);
        $component->assertDontSee('SECRET MARKING EXPLANATION');
        $snapshotHtml = html_entity_decode($component->html());
        $this->assertStringNotContainsString('original_correct_answers', $snapshotHtml);
        $this->assertStringNotContainsString('SECRET MARKING EXPLANATION', $snapshotHtml);
    }

    public function test_browser_cannot_replace_locked_question_state(): void
    {
        [$student, $assessment] = $this->examContext();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($student)
            ->test(CbtExamInterface::class, ['assessment' => $assessment->id])
            ->set('questions.0.original_correct_answers', [0]);
    }

    public function test_submission_is_graded_from_database_question_data(): void
    {
        [$student, $assessment, $question] = $this->examContext();

        $component = Livewire::actingAs($student)
            ->test(CbtExamInterface::class, ['assessment' => $assessment->id]);
        $displayedOptions = $component->get('questions')[0]['options'];
        $displayedWrongAnswer = array_search('A', $displayedOptions, true);

        $this->assertNotFalse($displayedWrongAnswer);

        $component
            ->call('startExam')
            ->call('submitExamWithAnswers', [$question->id => $displayedWrongAnswer]);

        $answer = StudentAnswer::query()->sole();

        $this->assertSame('0', $answer->answer);
        $this->assertFalse((bool) $answer->is_correct);
        $this->assertSame('0.00', (string) $answer->points_earned);
        $this->assertNotNull($answer->attempt_session_id);
        $this->assertTrue($component->get('pendingPublish'));
        $this->assertNull($component->get('results'));
    }

    public function test_attempt_session_ids_cannot_cross_student_boundaries(): void
    {
        [$studentA, $assessment] = $this->examContext();
        $studentB = $this->student($studentA->school, 'student-b');
        $sessionA = $this->attempt($assessment, $studentA);
        $sessionB = $this->attempt($assessment, $studentB);

        Auth::login($studentA);
        $component = new CbtExamInterface();
        $component->assessment = $assessment;
        $component->attemptSessionId = $sessionB->id;
        $method = new ReflectionMethod($component, 'currentAttemptSession');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($component));

        $component->attemptSessionId = $sessionA->id;
        $this->assertSame($sessionA->id, $method->invoke($component)?->id);
    }

    public function test_zero_answer_submission_is_a_real_completed_attempt(): void
    {
        [$student, $assessment] = $this->examContext();

        Livewire::actingAs($student)
            ->test(CbtExamInterface::class, ['assessment' => $assessment->id])
            ->call('startExam')
            ->call('submitExamWithAnswers', []);

        $session = AttemptSession::query()->sole();
        $answer = StudentAnswer::query()->sole();

        $this->assertSame('submitted', $session->status);
        $this->assertNull($answer->answer);
        $this->assertNotNull($answer->submitted_at);
        $this->assertSame(1, $assessment->fresh()->getStudentAttemptCount($student->id));
    }

    public function test_question_from_another_assessment_cannot_be_saved(): void
    {
        [$student, $assessment] = $this->examContext();
        $otherAssessment = $assessment->replicate(['slug']);
        $otherAssessment->title = 'Other CBT';
        $otherAssessment->save();
        $otherQuestion = Question::query()->create([
            'assessment_id' => $otherAssessment->id,
            'question_text' => 'Other assessment question',
            'question_type' => 'multiple_choice',
            'options' => ['A', 'B'],
            'correct_answers' => [0],
            'points' => 1,
            'order' => 1,
        ]);

        Livewire::actingAs($student)
            ->test(CbtExamInterface::class, ['assessment' => $assessment->id])
            ->call('startExam')
            ->call('saveAnswer', $otherQuestion->id, 0);

        $this->assertDatabaseMissing('student_answers', [
            'assessment_id' => $assessment->id,
            'question_id' => $otherQuestion->id,
        ]);
    }

    /**
     * @return array{User, Assessment, Question}
     */
    private function examContext(): array
    {
        $school = School::query()->create(['name' => 'CBT Integrity School', 'code' => 'CBT-INT']);
        $year = AcademicYear::query()->create([
            'start_year' => '2040',
            'stop_year' => '2041',
            'school_id' => $school->id,
        ]);
        $semester = Semester::query()->create([
            'name' => 'First Term',
            'academic_year_id' => $year->id,
            'school_id' => $school->id,
        ]);
        $group = ClassGroup::query()->create(['name' => 'JSS 1', 'school_id' => $school->id]);
        $class = MyClass::query()->create(['name' => 'JSS 1A', 'class_group_id' => $group->id]);
        $school->update(['academic_year_id' => $year->id, 'semester_id' => $semester->id]);
        $student = $this->student($school, 'student-a');
        $record = StudentRecord::query()->create([
            'user_id' => $student->id,
            'admission_number' => 'CBT-'.$student->id,
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
        $subject = Subject::query()->create([
            'name' => 'Computer Studies',
            'short_name' => 'COMP',
            'school_id' => $school->id,
            'my_class_id' => $class->id,
            'is_general' => false,
            'is_legacy' => false,
        ]);
        $assessment = Assessment::query()->create([
            'course_id' => $class->id,
            'lesson_id' => $subject->id,
            'academic_year_id' => $year->id,
            'semester_id' => $semester->id,
            'title' => 'Integrity CBT',
            'type' => 'quiz',
            'pass_percentage' => 50,
            'estimated_duration_minutes' => 30,
            'max_score' => 1,
            'max_attempts' => 1,
            'exam_published_at' => now(),
            'is_locked' => true,
            'shuffle_options' => true,
        ]);
        $question = Question::query()->create([
            'assessment_id' => $assessment->id,
            'question_text' => 'The stored correct answer is D.',
            'question_type' => 'multiple_choice',
            'options' => ['A', 'B', 'C', 'D'],
            'correct_answers' => [3],
            'explanation' => 'SECRET MARKING EXPLANATION',
            'points' => 1,
            'order' => 1,
        ]);

        return [$student, $assessment, $question];
    }

    private function student(School $school, string $label): User
    {
        $student = User::query()->forceCreate([
            'name' => ucfirst(str_replace('-', ' ', $label)),
            'email' => $label.'-'.uniqid().'@example.test',
            'email_verified_at' => now(),
            'password' => bcrypt('Password1!'),
            'school_id' => $school->id,
            'locked' => false,
            'requires_password_change' => false,
        ]);
        $student->assignRole(Role::findOrCreate('student', 'web'));

        return $student;
    }

    private function attempt(Assessment $assessment, User $student): AttemptSession
    {
        return AttemptSession::query()->create([
            'assessment_id' => $assessment->id,
            'user_id' => $student->id,
            'school_id' => $student->school_id,
            'attempt_number' => 1,
            'started_at' => now(),
            'expires_at' => now()->addHour(),
            'question_order' => $assessment->questions()->pluck('id')->all(),
            'answers_snapshot' => [],
        ]);
    }
}
