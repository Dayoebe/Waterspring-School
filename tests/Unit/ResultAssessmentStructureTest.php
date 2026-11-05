<?php

namespace Tests\Unit;

use App\Helpers\ScoreValidator;
use App\Models\Result;
use App\Services\CbtResultImporter;
use Illuminate\Filesystem\Filesystem;
use ReflectionClass;
use Tests\TestCase;

class ResultAssessmentStructureTest extends TestCase
{
    public function test_result_components_use_the_20_30_50_structure(): void
    {
        $this->assertSame([
            'ca1_score' => 20,
            'ca2_score' => 30,
            'exam_score' => 50,
        ], Result::SCORE_MAXIMUMS);

        $this->assertSame(100.0, Result::calculateTotal(20, 30, 50));
        $this->assertSame(52.5, Result::calculateTotal(12.5, 20, 20));
        $this->assertSame(100, ScoreValidator::calculateTotal(20, 30, 50));
    }

    public function test_cbt_imports_scale_to_the_same_component_maximums(): void
    {
        $maximums = (new ReflectionClass(CbtResultImporter::class))
            ->getConstant('TARGET_MAXIMUMS');

        $this->assertSame(Result::SCORE_MAXIMUMS, $maximums);
    }

    public function test_score_validator_rejects_marks_above_each_component_limit(): void
    {
        foreach ([
            [21, 20, 'CA 1'],
            [31, 30, 'CA 2'],
        ] as [$score, $maximum, $label]) {
            try {
                ScoreValidator::validateCA($score, $maximum, $label);
                $this->fail("{$label} accepted a score above {$maximum}.");
            } catch (\Exception $exception) {
                $this->assertStringContainsString("cannot exceed {$maximum}", $exception->getMessage());
            }
        }

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Exam score cannot exceed 50.');
        ScoreValidator::validateExam(51);
    }

    public function test_runtime_code_and_views_do_not_reference_removed_ca_components(): void
    {
        $filesystem = new Filesystem;

        foreach ([app_path(), resource_path('views')] as $path) {
            foreach ($filesystem->allFiles($path) as $file) {
                $contents = $filesystem->get($file->getPathname());

                $this->assertDoesNotMatchRegularExpression(
                    '/\bca[34]_score\b|\bCA\s?[34]\b/',
                    $contents,
                    "Legacy CA component remains in {$file->getRelativePathname()}."
                );
            }
        }
    }
}
