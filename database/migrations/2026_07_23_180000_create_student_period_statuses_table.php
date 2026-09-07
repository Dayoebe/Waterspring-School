<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('student_period_statuses')) {
            return;
        }

        Schema::create('student_period_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 32);
            $table->string('reason', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['student_record_id', 'academic_year_id'],
                'student_period_status_student_year_unique'
            );
            $table->index(
                ['school_id', 'academic_year_id', 'semester_id', 'status'],
                'student_period_status_lookup_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_period_statuses');
    }
};
