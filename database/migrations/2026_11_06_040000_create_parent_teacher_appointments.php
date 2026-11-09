<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_availability_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('mode', 24)->default('in_person');
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 24)->default('available');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['school_id', 'teacher_id', 'starts_at'], 'appointment_slots_teacher_start_index');
            $table->index(['school_id', 'status', 'starts_at'], 'appointment_slots_status_start_index');
        });

        Schema::create('parent_teacher_appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_availability_slot_id');
            $table->foreign('appointment_availability_slot_id', 'pta_slot_foreign')
                ->references('id')->on('appointment_availability_slots')->restrictOnDelete();
            $table->foreignId('parent_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->string('status', 24)->default('requested');
            $table->dateTime('requested_at');
            $table->dateTime('responded_at')->nullable();
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'parent_id', 'status'], 'appointments_parent_status_index');
            $table->index(['school_id', 'teacher_id', 'status'], 'appointments_teacher_status_index');
            $table->index(['appointment_availability_slot_id', 'status'], 'appointments_slot_status_index');
        });

        Schema::create('parent_teacher_appointment_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_teacher_appointment_id');
            $table->foreign('parent_teacher_appointment_id', 'pta_notes_appointment_foreign')
                ->references('id')->on('parent_teacher_appointments')->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_name');
            $table->string('type', 24);
            $table->text('note');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['parent_teacher_appointment_id', 'created_at'], 'appointment_notes_date_index');
        });

        Schema::create('appointment_notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_teacher_appointment_id');
            $table->foreign('parent_teacher_appointment_id', 'pta_deliveries_appointment_foreign')
                ->references('id')->on('parent_teacher_appointments')->cascadeOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 40);
            $table->string('deduplication_key')->unique();
            $table->string('status', 24)->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        $manage = Permission::findOrCreate('manage parent teacher appointments', 'web');
        $book = Permission::findOrCreate('book parent teacher appointment', 'web');
        foreach (['principal', 'admin', 'teacher'] as $roleName) {
            Role::query()->where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo($manage);
        }
        Role::query()->where('name', 'parent')->where('guard_name', 'web')->first()?->givePermissionTo($book);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_notification_deliveries');
        Schema::dropIfExists('parent_teacher_appointment_notes');
        Schema::dropIfExists('parent_teacher_appointments');
        Schema::dropIfExists('appointment_availability_slots');

        foreach (['manage parent teacher appointments', 'book parent teacher appointment'] as $permission) {
            Permission::query()->where('name', $permission)->where('guard_name', 'web')->delete();
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
