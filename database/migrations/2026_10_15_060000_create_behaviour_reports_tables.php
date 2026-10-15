<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('behaviour_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->string('teacher_context', 20);
            $table->string('category', 40);
            $table->string('severity', 20)->default('observation');
            $table->string('title');
            $table->date('occurred_on');
            $table->string('status', 20)->default('open');
            $table->foreignId('initiated_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_message_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'status', 'last_message_at'], 'behaviour_reports_school_status_index');
            $table->index(['student_id', 'last_message_at'], 'behaviour_reports_student_index');
            $table->index(['parent_id', 'last_message_at'], 'behaviour_reports_parent_index');
            $table->index(['teacher_id', 'last_message_at'], 'behaviour_reports_teacher_index');
        });

        Schema::create('behaviour_report_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('behaviour_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['behaviour_report_id', 'created_at'], 'behaviour_messages_thread_index');
            $table->index(['author_id', 'read_at'], 'behaviour_messages_unread_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('behaviour_report_messages');
        Schema::dropIfExists('behaviour_reports');
    }
};
