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
        Schema::create('transport_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 40);
            $table->string('vehicle_label')->nullable();
            $table->string('driver_name')->nullable();
            $table->string('driver_phone', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['school_id', 'code']);
        });

        Schema::create('transport_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transport_route_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->time('morning_time')->nullable();
            $table->time('afternoon_time')->nullable();
            $table->string('landmark')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['transport_route_id', 'sequence']);
        });

        Schema::create('student_transport_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('transport_route_id')->constrained()->restrictOnDelete();
            $table->foreignId('transport_stop_id')->constrained()->restrictOnDelete();
            $table->string('direction', 20)->default('both');
            $table->string('status', 20)->default('active');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['school_id', 'student_id', 'status'], 'transport_assignments_student_status_index');
        });

        Schema::create('authorized_pickup_guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('relationship', 80);
            $table->string('phone', 40);
            $table->string('pickup_code_hash');
            $table->boolean('is_active')->default(true);
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('failed_verification_attempts')->default(0);
            $table->dateTime('verification_locked_until')->nullable();
            $table->dateTime('last_verified_at')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'student_id', 'is_active'], 'pickup_guardians_student_active_index');
        });

        Schema::create('transport_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('student_transport_assignment_id')->nullable();
            $table->foreign('student_transport_assignment_id', 'transport_events_assignment_foreign')
                ->references('id')->on('student_transport_assignments')->nullOnDelete();
            $table->foreignId('authorized_pickup_guardian_id')->nullable();
            $table->foreign('authorized_pickup_guardian_id', 'transport_events_guardian_foreign')
                ->references('id')->on('authorized_pickup_guardians')->nullOnDelete();
            $table->foreignId('transport_route_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('transport_stop_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 32);
            $table->string('verification_method', 24)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recorder_name');
            $table->text('notes')->nullable();
            $table->dateTime('occurred_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['school_id', 'student_id', 'occurred_at'], 'transport_events_student_date_index');
            $table->index(['school_id', 'event_type', 'occurred_at'], 'transport_events_type_date_index');
        });

        Schema::create('transport_notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transport_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('deduplication_key')->unique();
            $table->string('status', 24)->default('pending');
            $table->text('error')->nullable();
            $table->timestamps();
        });

        $manage = Permission::findOrCreate('manage school transport', 'web');
        $view = Permission::findOrCreate('view child transport', 'web');
        foreach (['principal', 'admin'] as $roleName) {
            Role::query()->where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo($manage);
        }
        Role::query()->where('name', 'parent')->where('guard_name', 'web')->first()?->givePermissionTo($view);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_notification_deliveries');
        Schema::dropIfExists('transport_events');
        Schema::dropIfExists('authorized_pickup_guardians');
        Schema::dropIfExists('student_transport_assignments');
        Schema::dropIfExists('transport_stops');
        Schema::dropIfExists('transport_routes');

        Permission::query()->whereIn('name', ['manage school transport', 'view child transport'])->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
