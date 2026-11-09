<?php

namespace App\Services\Results;

use App\Models\AcademicYear;
use App\Models\MyClass;
use Illuminate\Support\Str;

final class AnnualResultExportFormatter
{
    public function formatReports(iterable $reports, callable $gradeResolver): array
    {
        return collect($reports)
            ->map(function (array $report) use ($gradeResolver) {
                $subjectScores = collect($report['subject_totals'])
                    ->map(function (array $subjectData) use ($gradeResolver) {
                        $average = (float) $subjectData['average'];

                        return [
                            'total' => $subjectData['total'],
                            'average' => $average,
                            'grade' => $gradeResolver($average),
                        ];
                    })
                    ->all();

                return [
                    'student' => $report['student'],
                    'term_scores' => $report['term_totals'],
                    'subject_scores' => $subjectScores,
                    'grand_total' => $report['grand_total'],
                    'annual_average' => $report['average_percentage'],
                    'position' => $report['rank'],
                ];
            })
            ->all();
    }

    public function filename(MyClass $class, AcademicYear $academicYear, string $extension): string
    {
        return str_replace(' ', '_', "{$class->name}_Annual_{$academicYear->name}.{$extension}");
    }

    public function hasCompleteTerms(iterable $semesters): bool
    {
        $termNames = collect($semesters)
            ->map(fn ($semester) => Str::lower(trim($semester->name)))
            ->values();

        return $termNames->count() === 3
            && $termNames->contains(fn ($name) => Str::contains($name, 'first'))
            && $termNames->contains(fn ($name) => Str::contains($name, 'second'))
            && $termNames->contains(fn ($name) => Str::contains($name, 'third'));
    }
}
