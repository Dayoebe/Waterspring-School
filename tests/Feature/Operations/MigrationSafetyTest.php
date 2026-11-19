<?php

namespace Tests\Feature\Operations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_exam_records_are_archived_instead_of_deleted(): void
    {
        $this->assertFalse(Schema::hasTable('exam_records'));
        $this->assertTrue(Schema::hasTable('legacy_exam_records'));
    }
}
