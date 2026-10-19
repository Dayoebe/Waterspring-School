<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_pilot_materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('my_class_id')->nullable()->constrained('my_classes')->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->string('title');
            $table->string('topic')->nullable();
            $table->string('status', 20)->default('draft');
            $table->json('content');
            $table->json('curriculum_sources')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'status', 'type']);
            $table->index(['my_class_id', 'subject_id']);
        });

        Schema::create('class_pilot_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_record_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('my_class_id')->nullable()->constrained('my_classes')->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->string('audience', 20);
            $table->string('feature', 40);
            $table->string('title');
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'user_id', 'audience']);
        });

        Schema::create('class_pilot_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('class_pilot_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role', 20);
            $table->longText('content');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['class_pilot_session_id', 'created_at']);
        });

        Schema::create('class_pilot_practice_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('class_pilot_material_id')->nullable()->constrained()->nullOnDelete();
            $table->string('activity_type', 40);
            $table->string('topic')->nullable();
            $table->unsignedInteger('score')->nullable();
            $table->unsignedInteger('max_score')->nullable();
            $table->json('answers')->nullable();
            $table->json('feedback')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['student_record_id', 'created_at']);
        });

        Schema::create('class_pilot_usage', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 30);
            $table->string('model', 80);
            $table->string('feature', 40);
            $table->string('status', 20);
            $table->unsignedInteger('input_characters')->default(0);
            $table->unsignedInteger('output_characters')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('request_id')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['school_id', 'feature', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_pilot_usage');
        Schema::dropIfExists('class_pilot_practice_attempts');
        Schema::dropIfExists('class_pilot_messages');
        Schema::dropIfExists('class_pilot_sessions');
        Schema::dropIfExists('class_pilot_materials');
    }
};
