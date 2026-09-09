<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->foreignId('my_class_id')->constrained('my_classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('instructions');
            $table->dateTime('due_at');
            $table->unsignedDecimal('max_score', 8, 2)->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->dateTime('published_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'academic_year_id', 'semester_id']);
            $table->index(['my_class_id', 'subject_id', 'due_at']);
        });

        Schema::create('assignment_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_record_id')->constrained()->cascadeOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamps();

            $table->unique(['assignment_id', 'student_record_id']);
        });

        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_record_id')->constrained()->cascadeOnDelete();
            $table->text('response_text')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('status')->default('submitted');
            $table->dateTime('submitted_at');
            $table->unsignedDecimal('score', 8, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('graded_at')->nullable();
            $table->timestamps();

            $table->unique(['assignment_id', 'student_record_id']);
            $table->index(['assignment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
        Schema::dropIfExists('assignment_recipients');
        Schema::dropIfExists('assignments');
    }
};
