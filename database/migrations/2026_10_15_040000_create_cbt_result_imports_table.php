<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbt_result_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->foreignId('student_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('result_id')->constrained()->cascadeOnDelete();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('score_column', 20);
            $table->unsignedSmallInteger('attempt_number');
            $table->decimal('percentage_score', 5, 2);
            $table->decimal('scaled_score', 5, 2);
            $table->timestamps();

            $table->unique(
                ['assessment_id', 'student_record_id', 'score_column'],
                'cbt_result_import_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbt_result_imports');
    }
};
