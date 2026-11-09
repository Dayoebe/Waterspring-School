<?php

namespace Tests\Unit;

use App\Models\AcademicYear;
use App\Models\MyClass;
use App\Services\Results\AnnualResultExportFormatter;
use PHPUnit\Framework\TestCase;

class AnnualResultExportFormatterTest extends TestCase
{
    public function test_it_requires_exactly_the_three_named_annual_terms(): void
    {
        $formatter = new AnnualResultExportFormatter;
        $terms = array_map(
            fn (string $name) => (object) ['name' => $name],
            ['First Term', 'Second Term', 'Third Term']
        );

        $this->assertTrue($formatter->hasCompleteTerms($terms));
        $this->assertFalse($formatter->hasCompleteTerms(array_slice($terms, 0, 2)));
        $this->assertFalse($formatter->hasCompleteTerms([
            (object) ['name' => 'First Term'],
            (object) ['name' => 'Second Term'],
            (object) ['name' => 'Summer Term'],
        ]));
    }

    public function test_it_shapes_annual_reports_without_owning_the_grade_policy(): void
    {
        $student = (object) ['id' => 42, 'name' => 'Test Student'];
        $formatter = new AnnualResultExportFormatter;

        $rows = $formatter->formatReports([[
            'student' => $student,
            'term_totals' => ['First Term' => 70],
            'subject_totals' => [5 => ['total' => 210, 'average' => 70]],
            'grand_total' => 210,
            'average_percentage' => 70,
            'rank' => 1,
        ]], fn (float $score): string => $score >= 70 ? 'B2' : 'F9');

        $this->assertSame($student, $rows[0]['student']);
        $this->assertSame('B2', $rows[0]['subject_scores'][5]['grade']);
        $this->assertSame(70, $rows[0]['annual_average']);
        $this->assertSame(1, $rows[0]['position']);
    }

    public function test_it_builds_a_stable_export_filename(): void
    {
        $class = new MyClass(['name' => 'Year 7']);
        $academicYear = new AcademicYear(['start_year' => 2026, 'stop_year' => 2027]);

        $this->assertSame(
            'Year_7_Annual_2026_-_2027.xlsx',
            (new AnnualResultExportFormatter)->filename($class, $academicYear, 'xlsx')
        );
    }
}
