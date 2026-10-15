<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schemes_of_work', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->foreignId('my_class_id')->constrained('my_classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('scope_key', 40);
            $table->string('title');
            $table->text('aim');
            $table->text('objectives');
            $table->text('teaching_approach')->nullable();
            $table->text('assessment_strategy')->nullable();
            $table->text('resources')->nullable();
            $table->text('learner_support')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['academic_year_id', 'semester_id', 'my_class_id', 'scope_key', 'teacher_id'], 'scheme_assignment_period_unique');
            $table->index(['school_id', 'academic_year_id', 'semester_id', 'published_at'], 'scheme_visibility_index');
        });

        Schema::create('scheme_topics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scheme_of_work_id')->constrained('schemes_of_work')->cascadeOnDelete();
            $table->unsignedSmallInteger('week_number');
            $table->date('scheduled_for')->nullable();
            $table->string('topic');
            $table->text('aim');
            $table->text('objectives');
            $table->text('content_outline')->nullable();
            $table->text('teacher_activities')->nullable();
            $table->text('learner_activities')->nullable();
            $table->text('resources')->nullable();
            $table->text('assessment')->nullable();
            $table->text('homework')->nullable();
            $table->string('status', 20)->default('planned');
            $table->text('completion_notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['scheme_of_work_id', 'week_number']);
            $table->index(['status', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheme_topics');
        Schema::dropIfExists('schemes_of_work');
    }
};
