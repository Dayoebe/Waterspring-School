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
}
