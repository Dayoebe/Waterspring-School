<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_term_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->text('goal');
            $table->text('objectives');
            $table->text('strengths')->nullable();
            $table->text('focus_areas')->nullable();
            $table->text('resources_needed')->nullable();
            $table->text('success_indicators')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['club_id', 'academic_year_id', 'semester_id'], 'club_term_plan_period_unique');
        });

        Schema::create('club_weekly_agendas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_term_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('week_number');
            $table->date('scheduled_for')->nullable();
            $table->string('title');
            $table->text('agenda');
            $table->string('status', 20)->default('planned');
            $table->text('completion_notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['club_term_plan_id', 'week_number']);
            $table->index(['status', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_weekly_agendas');
        Schema::dropIfExists('club_term_plans');
    }
};
