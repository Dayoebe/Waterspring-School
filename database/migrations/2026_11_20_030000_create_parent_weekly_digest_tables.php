<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parent_digest_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->unsignedTinyInteger('delivery_day')->default(1);
            $table->time('delivery_time')->default('07:00');
            $table->string('timezone', 64)->default('Africa/Lagos');
            $table->unsignedTinyInteger('upcoming_days')->default(7);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('parent_digest_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained('users')->cascadeOnDelete();
            $table->date('week_start_date');
            $table->date('week_end_date');
            $table->string('status', 20)->default('queued');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('child_count')->default(0);
            $table->string('payload_hash', 64)->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_code')->nullable();
            $table->timestamps();

            $table->unique(['guardian_id', 'week_start_date'], 'parent_digest_guardian_week_unique');
            $table->index(['school_id', 'status', 'week_start_date'], 'parent_digest_school_status_week_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_digest_deliveries');
        Schema::dropIfExists('parent_digest_settings');
    }
};
