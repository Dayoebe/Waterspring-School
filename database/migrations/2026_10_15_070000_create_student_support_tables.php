<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_support_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lead_teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('opened_by')->constrained('users')->cascadeOnDelete();
            $table->string('source_type', 40)->default('manual');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('category', 40);
            $table->string('priority', 20)->default('medium');
            $table->string('title');
            $table->text('concern');
            $table->text('goal');
            $table->string('status', 30)->default('monitoring');
            $table->date('review_on');
            $table->timestamp('parent_acknowledged_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'status', 'review_on'], 'support_cases_school_status_review_index');
            $table->index(['student_id', 'created_at'], 'support_cases_student_index');
            $table->index(['parent_id', 'status'], 'support_cases_parent_index');
            $table->index(['lead_teacher_id', 'status'], 'support_cases_teacher_index');
        });

        Schema::create('student_support_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_support_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('details')->nullable();
            $table->date('due_on');
            $table->string('status', 20)->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['owner_id', 'status', 'due_on'], 'support_actions_owner_status_index');
        });

        Schema::create('student_support_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_support_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('progress', 20)->default('note');
            $table->text('note');
            $table->boolean('parent_visible')->default(true);
            $table->timestamps();
            $table->index(['student_support_case_id', 'created_at'], 'support_updates_case_index');
        });

        Schema::create('student_support_mail_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_support_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->string('event', 40);
            $table->string('deduplication_key')->unique();
            $table->string('status', 20)->default('pending');
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['student_support_case_id', 'event'], 'support_mail_case_event_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_support_mail_deliveries');
        Schema::dropIfExists('student_support_updates');
        Schema::dropIfExists('student_support_actions');
        Schema::dropIfExists('student_support_cases');
    }
};
