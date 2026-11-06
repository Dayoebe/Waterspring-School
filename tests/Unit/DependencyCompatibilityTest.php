<?php

namespace Tests\Unit;

use App\Exports\ClassResultsExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class DependencyCompatibilityTest extends TestCase
{
    public function test_pdf_documents_can_be_rendered(): void
    {
        $document = Pdf::loadHTML('<h1>Watersprings compatibility check</h1>')->output();

        $this->assertStringStartsWith('%PDF-', $document);
        $this->assertGreaterThan(500, strlen($document));
    }

    public function test_class_results_can_be_exported_as_an_xlsx_workbook(): void
    {
        $subject = (object) ['id' => 10, 'name' => 'Mathematics'];
        $student = (object) ['user' => (object) ['name' => 'Test Student']];
        $export = ClassResultsExport::forTermly(collect([[
            'position' => 1,
            'student' => $student,
            'subject_scores' => [10 => ['score' => 87, 'grade' => 'A']],
            'total_score' => 87,
            'average' => 87,
        ]]), collect([$subject]), 'Year 7', 'First Term', '2026/2027');

        $workbook = Excel::raw($export, ExcelFormat::XLSX);

        $this->assertStringStartsWith('PK', $workbook);
        $this->assertGreaterThan(1_000, strlen($workbook));
    }
}
