<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_exam_participations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->foreignId('my_class_id')->constrained('my_classes')->cascadeOnDelete();
            $table->string('status', 32);
            $table->string('examination_name', 100)->nullable();
            $table->string('reason', 255);
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['school_id', 'academic_year_id', 'semester_id', 'my_class_id'],
                'class_exam_participation_period_unique'
            );
            $table->index(
                ['school_id', 'academic_year_id', 'semester_id', 'status'],
                'class_exam_participation_lookup_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_exam_participations');
    }
};
