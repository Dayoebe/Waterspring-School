<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_invoices', function (Blueprint $table) {
            $table->foreignId('my_class_id')
                ->nullable()
                ->after('user_id')
                ->constrained('my_classes')
                ->nullOnDelete();
            $table->foreignId('section_id')
                ->nullable()
                ->after('my_class_id')
                ->constrained('sections')
                ->nullOnDelete();
        });

        DB::table('fee_invoices')
            ->select(['id', 'user_id'])
            ->orderBy('id')
            ->chunkById(250, function ($invoices): void {
                $studentRecords = DB::table('student_records')
                    ->whereIn('user_id', $invoices->pluck('user_id'))
                    ->get(['user_id', 'my_class_id', 'section_id'])
                    ->keyBy('user_id');

                foreach ($invoices as $invoice) {
                    $studentRecord = $studentRecords->get($invoice->user_id);

                    if (! $studentRecord) {
                        continue;
                    }

                    DB::table('fee_invoices')
                        ->where('id', $invoice->id)
                        ->update([
                            'my_class_id' => $studentRecord->my_class_id,
                            'section_id' => $studentRecord->section_id,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('fee_invoices', function (Blueprint $table) {
            $table->dropForeign(['section_id']);
            $table->dropForeign(['my_class_id']);
            $table->dropColumn(['my_class_id', 'section_id']);
        });
    }
};
