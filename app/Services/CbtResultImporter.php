<?php

namespace App\Services;

use App\Models\Assessment\Assessment;
use App\Models\Result;
use App\Models\StudentRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CbtResultImporter
{
    private const TARGET_MAXIMUMS = [
        'ca1_score' => 10,
        'ca2_score' => 10,
        'ca3_score' => 10,
        'ca4_score' => 10,
        'exam_score' => 60,
    ];

    public function import(Assessment $assessment, string $target, int $importedBy): array
    {
        if (!array_key_exists($target, self::TARGET_MAXIMUMS)) {
            throw ValidationException::withMessages(['importTarget' => 'Choose a valid result column.']);
        }

        if (!$assessment->results_published_at) {
            throw ValidationException::withMessages(['importTarget' => 'Publish the CBT results before transferring them.']);
        }

        if (!$assessment->academic_year_id || !$assessment->semester_id || !$assessment->course_id || !$assessment->lesson_id) {
            throw ValidationException::withMessages(['importTarget' => 'This assessment is missing its class, subject, session, or term.']);
        }

        $assessment->loadMissing(['course.classGroup', 'questions:id,assessment_id,points', 'studentAnswers']);
        $maxPoints = (float) $assessment->questions->sum('points');

        if ($maxPoints <= 0) {
            throw ValidationException::withMessages(['importTarget' => 'This assessment has no scorable questions.']);
        }

        $submitted = $assessment->studentAnswers
            ->whereNotNull('submitted_at')
            ->groupBy('user_id');
        $activeStudentIds = StudentRecord::activeStudentRecordIdsForSchoolAcademicYear(
            $assessment->course?->classGroup?->school_id,
            $assessment->academic_year_id,
            $assessment->course_id
        );
        $studentRecords = StudentRecord::withoutGlobalScope('notGraduated')
            ->whereIn('id', $activeStudentIds)
            ->whereIn('user_id', $submitted->keys())
            ->get()
            ->keyBy('user_id');
        $maximum = self::TARGET_MAXIMUMS[$target];
        $imported = 0;
        $skipped = 0;

        DB::transaction(function () use ($assessment, $submitted, $studentRecords, $target, $maximum, $maxPoints, $importedBy, &$imported, &$skipped) {
            foreach ($submitted as $userId => $answers) {
                $student = $studentRecords->get((int) $userId);

                if (!$student || !$student->studentSubjects()->whereKey($assessment->lesson_id)->exists()) {
                    $skipped++;
                    continue;
                }

                $best = $answers
                    ->groupBy('attempt_number')
                    ->map(function ($attemptAnswers, $attemptNumber) use ($maxPoints) {
                        $percentage = min(100, round(((float) $attemptAnswers->sum('points_earned') / $maxPoints) * 100, 2));

                        return ['attempt' => (int) $attemptNumber, 'percentage' => $percentage];
                    })
                    ->sortByDesc('percentage')
                    ->first();

                if (!$best) {
                    $skipped++;
                    continue;
                }

                $scaled = round(($best['percentage'] / 100) * $maximum, 2);
                $result = Result::firstOrNew([
                    'student_record_id' => $student->id,
                    'subject_id' => $assessment->lesson_id,
                    'academic_year_id' => $assessment->academic_year_id,
                    'semester_id' => $assessment->semester_id,
                ]);
                $result->{$target} = $scaled;
                $result->total_score = collect(['ca1_score', 'ca2_score', 'ca3_score', 'ca4_score', 'exam_score'])
                    ->sum(fn ($column) => (float) ($result->{$column} ?? 0));
                $result->save();

                DB::table('cbt_result_imports')->updateOrInsert(
                    [
                        'assessment_id' => $assessment->id,
                        'student_record_id' => $student->id,
                        'score_column' => $target,
                    ],
                    [
                        'result_id' => $result->id,
                        'imported_by' => $importedBy,
                        'attempt_number' => $best['attempt'],
                        'percentage_score' => $best['percentage'],
                        'scaled_score' => $scaled,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                $imported++;
            }
        });

        return compact('imported', 'skipped');
    }
}
