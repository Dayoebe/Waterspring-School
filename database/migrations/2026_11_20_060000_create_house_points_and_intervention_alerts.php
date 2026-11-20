<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_houses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('colour', 20)->default('#2563eb');
            $table->string('motto', 180)->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['school_id', 'name']);
        });

        Schema::create('school_house_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_house_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->date('joined_on');
            $table->date('left_on')->nullable();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['student_id', 'academic_year_id'], 'house_membership_student_year_unique');
        });

        Schema::create('house_point_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('default_points');
            $table->unsignedSmallInteger('duplicate_window_days')->default(1);
            $table->unsignedSmallInteger('max_awards_per_student_per_term')->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['school_id', 'name']);
        });

        Schema::create('house_point_awards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('school_house_id')->constrained()->restrictOnDelete();
            $table->foreignId('house_point_rule_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('student_activity_record_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('kind', 12)->default('award');
            $table->unsignedSmallInteger('points');
            $table->string('reason', 500);
            $table->date('occurred_on');
            $table->string('status', 20)->default('pending');
            $table->string('deduplication_key', 64)->unique();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'status', 'occurred_on'], 'house_awards_status_date_idx');
        });

        Schema::create('house_point_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('house_point_award_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('school_house_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained()->restrictOnDelete();
            $table->integer('points');
            $table->string('reason', 500);
            $table->date('occurred_on');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->index(['school_house_id', 'academic_year_id', 'semester_id'], 'house_points_ranking_idx');
            $table->index(['student_id', 'occurred_on'], 'house_points_student_date_idx');
        });

        Schema::create('house_challenges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('title', 180);
            $table->text('description');
            $table->dateTime('starts_at');
            $table->dateTime('deadline_at');
            $table->string('status', 20)->default('draft');
            $table->unsignedSmallInteger('completion_points')->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('house_challenge_participations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('house_challenge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('school_house_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('joined');
            $table->decimal('score', 10, 2)->nullable();
            $table->unsignedInteger('position')->nullable();
            $table->text('result_note')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['house_challenge_id', 'student_id'], 'challenge_student_unique');
        });

        Schema::create('house_badges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('icon', 80)->default('fa-medal');
            $table->text('description')->nullable();
            $table->unsignedInteger('minimum_points')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['school_id', 'name']);
        });

        Schema::create('student_house_badges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('house_badge_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('awarded_by')->constrained('users')->restrictOnDelete();
            $table->string('reason', 500);
            $table->timestamp('awarded_at');
            $table->timestamps();
            $table->unique(['house_badge_id', 'student_id', 'academic_year_id'], 'student_badge_year_unique');
        });

        Schema::create('intervention_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('indicator', 40);
            $table->string('operator', 8)->default('>=');
            $table->decimal('threshold', 10, 2);
            $table->unsignedSmallInteger('lookback_days')->default(30);
            $table->unsignedSmallInteger('minimum_expected_records')->default(1);
            $table->string('severity', 20)->default('medium');
            $table->text('explanation');
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['school_id', 'name']);
        });

        Schema::create('student_intervention_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('intervention_rule_id')->constrained()->restrictOnDelete();
            $table->string('severity', 20);
            $table->decimal('observed_value', 10, 2);
            $table->boolean('data_complete')->default(false);
            $table->json('evidence');
            $table->json('missing_data')->nullable();
            $table->string('status', 20)->default('new');
            $table->string('fingerprint', 64)->unique();
            $table->timestamp('detected_at');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->text('dismissal_reason')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'status', 'severity'], 'intervention_alert_status_idx');
            $table->index(['student_id', 'detected_at'], 'intervention_alert_student_idx');
        });

        Schema::create('intervention_follow_ups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_intervention_alert_id')->constrained()->restrictOnDelete();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 180);
            $table->text('notes')->nullable();
            $table->date('due_on');
            $table->string('status', 20)->default('pending');
            $table->timestamp('escalated_at')->nullable();
            $table->foreignId('escalated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['owner_id', 'status', 'due_on'], 'intervention_followup_owner_idx');
        });

        Schema::create('intervention_alert_updates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_intervention_alert_id')->constrained()->restrictOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->string('event', 30);
            $table->text('note');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intervention_alert_updates');
        Schema::dropIfExists('intervention_follow_ups');
        Schema::dropIfExists('student_intervention_alerts');
        Schema::dropIfExists('intervention_rules');
        Schema::dropIfExists('student_house_badges');
        Schema::dropIfExists('house_badges');
        Schema::dropIfExists('house_challenge_participations');
        Schema::dropIfExists('house_challenges');
        Schema::dropIfExists('house_point_transactions');
        Schema::dropIfExists('house_point_awards');
        Schema::dropIfExists('house_point_rules');
        Schema::dropIfExists('school_house_memberships');
        Schema::dropIfExists('school_houses');
    }
};
