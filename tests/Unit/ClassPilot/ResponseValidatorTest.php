<?php

namespace Tests\Unit\ClassPilot;

use App\Services\ClassPilot\Exceptions\ClassPilotException;
use App\Services\ClassPilot\ResponseValidator;
use Tests\TestCase;

class ResponseValidatorTest extends TestCase
{
    public function test_it_accepts_a_well_formed_lesson(): void
    {
        $result = app(ResponseValidator::class)->validate('lesson', [
            'title' => 'Fractions', 'summary' => 'An introduction.',
            'objectives' => ['Identify equivalent fractions'],
            'sections' => [['heading' => 'Explore', 'content' => 'Use fraction strips.']],
        ]);
        $this->assertSame('Fractions', $result['title']);
    }

    public function test_it_rejects_an_assessment_with_inconsistent_marks(): void
    {
        $this->expectException(ClassPilotException::class);
        app(ResponseValidator::class)->validate('assessment', [
            'title' => 'Check', 'instructions' => 'Answer all.',
            'questions' => [['prompt' => 'What is 1+1?', 'marks' => 2]], 'total_marks' => 10,
        ]);
    }

    public function test_it_rejects_malformed_tutor_output(): void
    {
        $this->expectException(ClassPilotException::class);
        app(ResponseValidator::class)->validate('tutor', ['suggested_next_steps' => []]);
    }

    public function test_it_accepts_a_complete_syllabus_draft(): void
    {
        $result = app(ResponseValidator::class)->validate('syllabus', $this->syllabusDraft());

        $this->assertSame('A practical course description.', $result['description']);
        $this->assertArrayHasKey('references', $result);
    }

    public function test_it_rejects_an_incomplete_syllabus_draft(): void
    {
        $this->expectException(ClassPilotException::class);

        app(ResponseValidator::class)->validate('syllabus', [
            'description' => 'Only one field was returned.',
        ]);
    }

    public function test_it_accepts_scheme_and_weekly_topic_drafts(): void
    {
        $scheme = app(ResponseValidator::class)->validate('scheme', [
            'aim' => 'Build secure understanding.',
            'objectives' => 'Explain and apply the key ideas.',
            'teaching_approach' => 'Model, discuss, practise, and reflect.',
            'assessment_strategy' => 'Use observation, short tasks, and a final application.',
            'resources' => 'Teacher-created examples and local materials.',
            'learner_support' => 'Scaffolds, peer support, and extension tasks.',
        ]);
        $topic = app(ResponseValidator::class)->validate('scheme_topic', [
            'aim' => 'Understand equal parts.',
            'objectives' => 'Identify and compare simple fractions.',
            'content_outline' => 'Meaning, representation, and comparison of fractions.',
            'teacher_activities' => 'Model equal sharing with concrete materials.',
            'learner_activities' => 'Build and compare fraction models.',
            'resources' => 'Paper shapes and fraction strips.',
            'assessment' => 'Label and compare two fraction models.',
            'homework' => 'Find three examples of fractions at home.',
        ]);

        $this->assertSame('Build secure understanding.', $scheme['aim']);
        $this->assertSame('Understand equal parts.', $topic['aim']);
    }

    public function test_it_accepts_club_profile_activity_plan_and_agenda_drafts(): void
    {
        $validator = app(ResponseValidator::class);

        $this->assertSame('Creative', $validator->validate('club_profile', [
            'category' => 'Creative',
            'description' => 'Students explore storytelling and publishing.',
        ])['category']);
        $this->assertArrayHasKey('description', $validator->validate('club_activity', [
            'description' => 'Students prepare and share a short club showcase.',
        ]));
        $this->assertArrayHasKey('success_indicators', $validator->validate('club_term_plan', [
            'objectives' => 'Develop skills through purposeful weekly practice.',
            'strengths' => 'Curiosity and willingness to collaborate.',
            'focus_areas' => 'Planning, practice, and reflection.',
            'resources_needed' => 'Coordinator-reviewed materials appropriate to each session.',
            'success_indicators' => 'Students demonstrate progress through completed club work.',
        ]));
        $this->assertArrayHasKey('agenda', $validator->validate('club_agenda', [
            'agenda' => 'Welcome, guided practice, collaborative task, and reflection.',
        ]));
    }

    private function syllabusDraft(): array
    {
        return [
            'description' => 'A practical course description.',
            'overview' => 'Explore the central ideas in a clear learning journey.',
            'aims' => 'Build understanding and confident application.',
            'learning_outcomes' => 'Explain concepts and apply them to familiar problems.',
            'scope_and_sequence' => "Weeks 1-3: Foundations\nWeeks 4-8: Application",
            'prerequisites' => 'Relevant foundational knowledge.',
            'teaching_methodology' => 'Modelling, guided practice, and discussion.',
            'assessment_plan' => 'Diagnostic, formative, and summative checks.',
            'learning_resources' => 'Teacher-created examples and local materials.',
            'differentiation' => 'Scaffolds for support and extension tasks.',
            'cross_curricular_links' => 'Connections to communication and daily life.',
            'homework_policy' => 'Short purposeful practice with feedback.',
            'references' => '',
        ];
    }
}
