<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('assignments', 'notifications_enabled')) {
            Schema::table('assignments', function (Blueprint $table): void {
                $table->boolean('notifications_enabled')->default(true)->after('published_at');
            });
        }

        if (! Schema::hasTable('assignment_notification_deliveries')) {
            Schema::create('assignment_notification_deliveries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
                $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
                $table->string('event', 40);
                $table->string('deduplication_key')->unique();
                $table->timestamp('sent_at');
                $table->timestamps();

                $table->index(['assignment_id', 'event']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_notification_deliveries');

        if (Schema::hasColumn('assignments', 'notifications_enabled')) {
            Schema::table('assignments', function (Blueprint $table): void {
                $table->dropColumn('notifications_enabled');
            });
        }
    }
};
