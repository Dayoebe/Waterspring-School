<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clubs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category', 80)->nullable();
            $table->text('description');
            $table->string('meeting_day', 20)->nullable();
            $table->time('meeting_time')->nullable();
            $table->string('meeting_location')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->string('colour', 20)->default('sky');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'name']);
            $table->index(['school_id', 'is_active']);
        });

        Schema::create('club_instructors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_lead')->default(false);
            $table->timestamps();
            $table->unique(['club_id', 'teacher_id']);
        });

        Schema::create('club_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->date('joined_on');
            $table->string('status', 20)->default('active');
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['club_id', 'student_id']);
            $table->index(['student_id', 'status']);
        });

        Schema::create('club_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('location')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['club_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_activities');
        Schema::dropIfExists('club_memberships');
        Schema::dropIfExists('club_instructors');
        Schema::dropIfExists('clubs');
    }
};
