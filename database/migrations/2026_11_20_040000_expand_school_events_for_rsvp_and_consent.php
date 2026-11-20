<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_events', function (Blueprint $table): void {
            $table->string('title', 180)->change();
            $table->string('location', 180)->nullable()->change();
            $table->string('status', 20)->default('draft')->after('category');
            $table->unsignedInteger('capacity')->nullable()->after('location');
            $table->boolean('requires_rsvp')->default(false)->after('capacity');
            $table->boolean('requires_consent')->default(false)->after('requires_rsvp');
            $table->boolean('requires_emergency_contact')->default(false)->after('requires_consent');
            $table->dateTime('response_deadline')->nullable()->after('requires_emergency_contact');
            $table->text('cancellation_reason')->nullable()->after('audiences');
            $table->timestamp('published_at')->nullable()->after('cancellation_reason');
            $table->foreignId('published_by')->nullable()->after('published_at')->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('published_by');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->foreignId('rescheduled_from_id')->nullable()->after('cancelled_by')->constrained('school_events')->nullOnDelete();
            $table->index(['school_id', 'status', 'starts_at'], 'school_events_school_status_starts_index');
        });

        DB::table('school_events')->update([
            'status' => 'published',
            'published_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)'),
            'published_by' => DB::raw('created_by'),
        ]);

        Schema::create('school_event_classes', function (Blueprint $table): void {
            $table->foreignId('school_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('my_class_id')->constrained('my_classes')->cascadeOnDelete();
            $table->primary(['school_event_id', 'my_class_id']);
        });

        Schema::create('school_event_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('guardian_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('participation_status', 24)->default('pending');
            $table->string('consent_status', 24)->default('not_required');
            $table->foreignId('consented_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('consented_at')->nullable();
            $table->string('emergency_contact_name', 255)->nullable();
            $table->string('emergency_contact_phone', 40)->nullable();
            $table->string('emergency_contact_relationship', 80)->nullable();
            $table->text('guardian_note')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->string('attendance_status', 20)->default('pending');
            $table->foreignId('attendance_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('attendance_confirmed_at')->nullable();
            $table->timestamps();

            $table->unique(['school_event_id', 'student_id'], 'school_event_student_unique');
            $table->index(['school_event_id', 'participation_status'], 'school_event_participation_index');
            $table->index(['guardian_id', 'responded_at'], 'school_event_guardian_response_index');
        });

        Schema::create('school_event_reminder_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_event_id');
            $table->unsignedBigInteger('school_event_response_id');
            $table->unsignedBigInteger('guardian_id');
            $table->string('reminder_type', 30);
            $table->string('status', 20)->default('queued');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_code')->nullable();
            $table->timestamps();

            $table->unique(['school_event_response_id', 'guardian_id', 'reminder_type'], 'school_event_reminder_unique');
            $table->index(['school_event_id', 'status'], 'school_event_reminder_status_index');
            $table->foreign('school_event_id', 'event_reminder_event_fk')
                ->references('id')->on('school_events')->cascadeOnDelete();
            $table->foreign('school_event_response_id', 'event_reminder_response_fk')
                ->references('id')->on('school_event_responses')->cascadeOnDelete();
            $table->foreign('guardian_id', 'event_reminder_guardian_fk')
                ->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_event_reminder_deliveries');
        Schema::dropIfExists('school_event_responses');
        Schema::dropIfExists('school_event_classes');
        Schema::table('school_events', function (Blueprint $table): void {
            $table->dropIndex('school_events_school_status_starts_index');
            $table->dropForeign(['published_by']);
            $table->dropForeign(['cancelled_by']);
            $table->dropForeign(['rescheduled_from_id']);
            $table->dropColumn([
                'status', 'capacity', 'requires_rsvp', 'requires_consent', 'requires_emergency_contact',
                'response_deadline', 'cancellation_reason', 'published_at', 'published_by',
                'cancelled_at', 'cancelled_by', 'rescheduled_from_id',
            ]);
        });
    }
};
