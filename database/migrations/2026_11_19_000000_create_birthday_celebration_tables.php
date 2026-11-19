<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('birthday_is_private')->default(false)->after('birthday');
            $table->index(['school_id', 'birthday', 'birthday_is_private'], 'users_school_birthday_visibility_index');
        });

        Schema::create('birthday_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('feature_enabled')->default(true);
            $table->boolean('show_students')->default(true);
            $table->boolean('show_staff')->default(true);
            $table->string('viewer_scope', 30)->default('authenticated');
            $table->boolean('wishes_enabled')->default(true);
            $table->boolean('automated_emails_enabled')->default(false);
            $table->boolean('automated_student_emails')->default(false);
            $table->boolean('automated_staff_emails')->default(false);
            $table->time('delivery_time')->default('08:00:00');
            $table->string('timezone', 64)->default('Africa/Lagos');
            $table->string('sender_name')->nullable();
            $table->string('sender_email')->nullable();
            $table->string('automated_subject', 160)->default('Happy Birthday, {name}!');
            $table->text('automated_message')->nullable();
            $table->boolean('public_visibility_enabled')->default(false);
            $table->boolean('greeting_wall_enabled')->default(false);
            $table->timestamps();
        });

        Schema::create('birthday_email_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('celebrant_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 20);
            $table->unsignedSmallInteger('birthday_year');
            $table->string('recipient_type', 20);
            $table->string('status', 20)->default('queued');
            $table->string('deduplication_key')->nullable()->unique();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->timestamps();
            $table->index(['school_id', 'kind', 'birthday_year', 'status'], 'birthday_delivery_status_index');
            $table->index(['sender_id', 'created_at'], 'birthday_sender_rate_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('birthday_email_deliveries');
        Schema::dropIfExists('birthday_settings');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_school_birthday_visibility_index');
            $table->dropColumn('birthday_is_private');
        });
    }
};
