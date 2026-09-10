<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 40)->default('event');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->boolean('all_day')->default(false);
            $table->string('location')->nullable();
            $table->json('audiences')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'starts_at']);
            $table->index(['school_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_events');
    }
};
