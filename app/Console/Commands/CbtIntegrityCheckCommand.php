<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class CbtIntegrityCheckCommand extends Command
{
    protected $signature = 'cbt:integrity-check {--assessment= : Limit checks to one assessment ID}';

    protected $description = 'Run read-only CBT ownership, attempt, and result-integrity checks';

    public function handle(): int
    {
        $assessmentId = filter_var($this->option('assessment'), FILTER_VALIDATE_INT) ?: null;
        $checks = [
            $this->check(
                'Answer belongs to assessment question',
                DB::table('student_answers as answers')
                    ->join('questions', 'questions.id', '=', 'answers.question_id')
                    ->whereColumn('questions.assessment_id', '<>', 'answers.assessment_id'),
                $assessmentId,
                'answers.assessment_id'
            ),
            $this->check(
                'Attempt school matches student school',
                DB::table('assessment_attempt_sessions as attempts')
                    ->join('users', 'users.id', '=', 'attempts.user_id')
                    ->whereColumn('attempts.school_id', '<>', 'users.school_id'),
                $assessmentId,
                'attempts.assessment_id'
            ),
            $this->check(
                'Answer identity matches linked attempt',
                DB::table('student_answers as answers')
                    ->join('assessment_attempt_sessions as attempts', 'attempts.id', '=', 'answers.attempt_session_id')
                    ->where(function (Builder $query): void {
                        $query->whereColumn('answers.user_id', '<>', 'attempts.user_id')
                            ->orWhereColumn('answers.assessment_id', '<>', 'attempts.assessment_id')
                            ->orWhereColumn('answers.attempt_number', '<>', 'attempts.attempt_number');
                    }),
                $assessmentId,
                'answers.assessment_id'
            ),
            $this->check(
                'Submitted answers link to an attempt',
                DB::table('student_answers as answers')
                    ->whereNotNull('answers.submitted_at')
                    ->whereNull('answers.attempt_session_id'),
                $assessmentId,
                'answers.assessment_id'
            ),
            $this->groupedCheck(
                'Only one active attempt per student and paper',
                DB::table('assessment_attempt_sessions as attempts')
                    ->where('attempts.status', 'in_progress')
                    ->groupBy('attempts.assessment_id', 'attempts.user_id')
                    ->havingRaw('COUNT(*) > 1'),
                $assessmentId,
                'attempts.assessment_id'
            ),
            $this->check(
                'No expired attempt remains in progress',
                DB::table('assessment_attempt_sessions as attempts')
                    ->where('attempts.status', 'in_progress')
                    ->where('attempts.expires_at', '<=', now()),
                $assessmentId,
                'attempts.assessment_id'
            ),
            $this->check(
                'Closed attempts contain submitted answer rows',
                DB::table('assessment_attempt_sessions as attempts')
                    ->whereIn('attempts.status', ['submitted', 'expired'])
                    ->whereNotExists(function ($query): void {
                        $query->selectRaw('1')
                            ->from('student_answers as answers')
                            ->whereColumn('answers.attempt_session_id', 'attempts.id')
                            ->whereNotNull('answers.submitted_at');
                    }),
                $assessmentId,
                'attempts.assessment_id'
            ),
            $this->check(
                'Published papers contain questions',
                DB::table('assessments')
                    ->whereNotNull('assessments.exam_published_at')
                    ->whereNull('assessments.deleted_at')
                    ->whereNotExists(function ($query): void {
                        $query->selectRaw('1')
                            ->from('questions')
                            ->whereColumn('questions.assessment_id', 'assessments.id')
                            ->whereNull('questions.deleted_at');
                    }),
                $assessmentId,
                'assessments.id'
            ),
        ];

        $this->table(['Integrity check', 'Violations', 'Status'], array_map(
            fn (array $check): array => [
                $check['name'],
                $check['violations'],
                $check['violations'] === 0 ? '<fg=green>PASS</>' : '<fg=red>FAIL</>',
            ],
            $checks
        ));

        $violations = array_sum(array_column($checks, 'violations'));
        if ($violations > 0) {
            $this->error("CBT integrity check failed with {$violations} violation(s).");

            return self::FAILURE;
        }

        $this->info('CBT integrity checks passed.');

        return self::SUCCESS;
    }

    private function check(string $name, Builder $query, ?int $assessmentId, string $assessmentColumn): array
    {
        if ($assessmentId) {
            $query->where($assessmentColumn, $assessmentId);
        }

        return ['name' => $name, 'violations' => $query->count()];
    }

    private function groupedCheck(string $name, Builder $query, ?int $assessmentId, string $assessmentColumn): array
    {
        if ($assessmentId) {
            $query->where($assessmentColumn, $assessmentId);
        }

        return [
            'name' => $name,
            'violations' => DB::query()->fromSub($query->selectRaw('1'), 'violations')->count(),
        ];
    }
}
