<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('open_learning_records', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->foreignId('my_class_id')->constrained('my_classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scheme_topic_id')->constrained('scheme_topics')->cascadeOnDelete();
            $table->string('title');
            $table->string('teaching_style', 30);
            $table->unsignedTinyInteger('progress_percentage')->default(0);
            $table->unsignedSmallInteger('current_section')->default(1);
            $table->json('lesson_state');
            $table->json('conversation_state')->nullable();
            $table->json('assessment_state')->nullable();
            $table->timestamp('last_activity_at');
            $table->timestamps();

            $table->index(['user_id', 'last_activity_at']);
            $table->index(['school_id', 'scheme_topic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('open_learning_records');
    }
};
