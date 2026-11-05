<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('results')) {
            return;
        }

        $legacyColumns = collect(['ca3_score', 'ca4_score'])
            ->filter(fn (string $column) => Schema::hasColumn('results', $column))
            ->values();

        if ($legacyColumns->isEmpty()) {
            return;
        }

        if (! Schema::hasTable('result_legacy_assessment_scores')) {
            Schema::create('result_legacy_assessment_scores', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('result_id')->unique();
                $table->decimal('ca3_score', 5, 2)->nullable();
                $table->decimal('ca4_score', 5, 2)->nullable();
                $table->decimal('original_total_score', 5, 2)->nullable();
                $table->timestamp('archived_at');
            });
        }

        DB::table('results')
            ->select(['id', 'ca3_score', 'ca4_score', 'total_score'])
            ->orderBy('id')
            ->chunkById(500, function ($results) {
                $rows = $results->map(fn ($result) => [
                    'result_id' => $result->id,
                    'ca3_score' => $result->ca3_score,
                    'ca4_score' => $result->ca4_score,
                    'original_total_score' => $result->total_score,
                    'archived_at' => now(),
                ])->all();

                if ($rows !== []) {
                    DB::table('result_legacy_assessment_scores')->upsert(
                        $rows,
                        ['result_id'],
                        ['ca3_score', 'ca4_score', 'original_total_score', 'archived_at']
                    );
                }
            });

        Schema::table('results', function (Blueprint $table) use ($legacyColumns) {
            $table->dropColumn($legacyColumns->all());
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('results')) {
            return;
        }

        Schema::table('results', function (Blueprint $table) {
            if (! Schema::hasColumn('results', 'ca3_score')) {
                $table->decimal('ca3_score', 5, 2)->default(0)->after('ca2_score');
            }

            if (! Schema::hasColumn('results', 'ca4_score')) {
                $table->decimal('ca4_score', 5, 2)->default(0)->after('ca3_score');
            }
        });

        if (Schema::hasTable('result_legacy_assessment_scores')) {
            DB::table('result_legacy_assessment_scores')
                ->orderBy('result_id')
                ->chunkById(500, function ($archivedScores) {
                    foreach ($archivedScores as $archived) {
                        DB::table('results')
                            ->where('id', $archived->result_id)
                            ->update([
                                'ca3_score' => $archived->ca3_score,
                                'ca4_score' => $archived->ca4_score,
                                'total_score' => $archived->original_total_score,
                            ]);
                    }
                }, 'result_id');

            Schema::drop('result_legacy_assessment_scores');
        }
    }
};
