<?php

namespace App\Services\OpenLearning;

use App\Models\SchemeTopic;

class AssessmentMarker
{
    public function __construct(private Tutor $tutor) {}

    public function mark(array $state, SchemeTopic $topic, array $assessment, array $answers): array
    {
        $results = [];
        $subjective = [];

        foreach ($assessment['questions'] as $question) {
            $answer = trim((string) ($answers[$question['id']] ?? ''));
            $base = [
                'id' => $question['id'],
                'type' => $question['type'],
                'prompt' => $question['prompt'],
                'answer' => $answer,
                'expected_answer' => $question['expected_answer'],
                'explanation' => $question['explanation'],
                'marks' => (int) $question['marks'],
            ];

            if (in_array($question['type'], ['multiple_choice', 'true_false', 'fill_gap'], true)) {
                $correct = $this->matches($answer, $question['accepted_answers']);
                $results[$question['id']] = array_merge($base, [
                    'awarded' => $correct ? (int) $question['marks'] : 0,
                    'correct' => $correct,
                    'feedback' => $correct
                        ? 'Correct. '.$question['explanation']
                        : 'That response does not match the expected answer. '.$question['explanation'],
                ]);

                continue;
            }

            if ($answer === '') {
                $results[$question['id']] = array_merge($base, [
                    'awarded' => 0,
                    'correct' => false,
                    'feedback' => 'No answer was provided. Review the expected answer and try a similar question.',
                ]);

                continue;
            }

            $subjective[] = [
                'id' => $question['id'],
                'prompt' => $question['prompt'],
                'learner_answer' => $answer,
                'expected_answer' => $question['expected_answer'],
                'marking_guide' => $question['marking_guide'],
                'max_marks' => (int) $question['marks'],
            ];
            $results[$question['id']] = $base;
        }

        $evaluations = collect($this->tutor->markSubjective($state, $topic, $subjective)['evaluations'] ?? [])->keyBy('id');
        foreach ($subjective as $item) {
            $evaluation = $evaluations->get($item['id']);
            $awarded = min((int) ($evaluation['awarded'] ?? 0), $item['max_marks']);
            $results[$item['id']] = array_merge($results[$item['id']], [
                'awarded' => $awarded,
                'correct' => (bool) ($evaluation['correct'] ?? false),
                'feedback' => (string) ($evaluation['feedback'] ?? 'The teaching service could not provide detailed feedback for this response.'),
            ]);
        }

        $ordered = collect($assessment['questions'])->map(fn ($question) => $results[$question['id']])->values()->all();
        $score = collect($ordered)->sum('awarded');
        $total = collect($ordered)->sum('marks');
        $percentage = $total > 0 ? (int) round(($score / $total) * 100) : 0;

        return [
            'score' => $score,
            'total' => $total,
            'percentage' => $percentage,
            'results' => $ordered,
            'recommendations' => $this->recommendations($percentage, $ordered),
        ];
    }

    private function matches(string $answer, array $accepted): bool
    {
        $normalise = static fn (string $value): string => mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $value)));
        $answer = $normalise($answer);

        return $answer !== '' && collect($accepted)->map($normalise)->contains($answer);
    }

    private function recommendations(int $percentage, array $results): array
    {
        $incorrect = collect($results)->where('correct', false)->pluck('prompt')->take(3)->values()->all();
        $guidance = $percentage >= 80
            ? 'Strong understanding. Review the corrections, then try a more challenging assessment.'
            : ($percentage >= 50
                ? 'Good progress. Revisit the sections connected to the missed questions and try again.'
                : 'Take your time: review the lesson examples, ask the companion for simpler explanations, then retry when ready.');

        return ['guidance' => $guidance, 'review_questions' => $incorrect];
    }
}
