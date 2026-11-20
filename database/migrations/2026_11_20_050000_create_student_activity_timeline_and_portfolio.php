<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parent_records', function (Blueprint $table): void {
            $table->boolean('can_view_growth_timeline')->default(false)->after('can_view_portfolio');
        });

        Schema::create('student_activity_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->string('classification', 24);
            $table->string('category', 60);
            $table->string('title', 180)->nullable();
            $table->text('description')->nullable();
            $table->date('occurred_on');
            $table->foreignId('academic_year_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('club_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sport', 120)->nullable();
            $table->foreignId('school_event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_recognition_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('approval_status', 20)->default('pending');
            $table->text('review_note')->nullable();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('observed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->boolean('eligible_timeline')->default(true);
            $table->boolean('eligible_portfolio')->default(false);
            $table->boolean('eligible_celebration')->default(false);
            $table->boolean('eligible_digest')->default(false);
            $table->boolean('eligible_display')->default(false);
            $table->boolean('eligible_house_points')->default(false);
            $table->boolean('eligible_memory_book')->default(false);
            $table->timestamps();

            $table->index(['school_id', 'student_id', 'occurred_on'], 'activity_school_student_date_idx');
            $table->index(['school_id', 'approval_status', 'category'], 'activity_school_status_category_idx');
            $table->index(['academic_year_id', 'semester_id'], 'activity_academic_period_idx');
        });

        Schema::create('student_activity_record_media', function (Blueprint $table): void {
            $table->unsignedBigInteger('student_activity_record_id');
            $table->unsignedBigInteger('media_asset_id');
            $table->foreignId('added_by')->constrained('users')->restrictOnDelete();
            $table->string('caption', 255)->nullable();
            $table->timestamps();
            $table->primary(['student_activity_record_id', 'media_asset_id'], 'activity_media_primary');
            $table->foreign('student_activity_record_id', 'activity_media_record_fk')->references('id')->on('student_activity_records')->cascadeOnDelete();
            $table->foreign('media_asset_id', 'activity_media_asset_fk')->references('id')->on('media_assets')->restrictOnDelete();
        });

        Schema::create('student_portfolio_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('per_student_quota_bytes')->default(262144000);
            $table->unsignedBigInteger('per_file_max_bytes')->default(26214400);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('student_portfolio_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('student_activity_record_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 60);
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('submission_source', 20);
            $table->string('status', 20)->default('pending');
            $table->string('visibility', 20)->default('private');
            $table->text('teacher_comment')->nullable();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'student_id', 'status'], 'portfolio_school_student_status_idx');
            $table->index(['academic_year_id', 'semester_id'], 'portfolio_academic_period_idx');
        });

        Schema::create('student_portfolio_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('student_portfolio_item_id');
            $table->unsignedBigInteger('media_asset_id');
            $table->unsignedInteger('version_number');
            $table->string('change_summary', 500)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['student_portfolio_item_id', 'version_number'], 'portfolio_item_version_unique');
            $table->foreign('student_portfolio_item_id', 'portfolio_version_item_fk')->references('id')->on('student_portfolio_items')->cascadeOnDelete();
            $table->foreign('media_asset_id', 'portfolio_version_media_fk')->references('id')->on('media_assets')->restrictOnDelete();
        });

        DB::table('student_recognitions')->orderBy('id')->each(function (object $recognition): void {
            DB::table('student_activity_records')->insert([
                'school_id' => $recognition->school_id,
                'student_id' => $recognition->student_id,
                'classification' => 'verified',
                'category' => 'recognition',
                'occurred_on' => $recognition->week_start_date,
                'academic_year_id' => $recognition->academic_year_id,
                'semester_id' => $recognition->semester_id,
                'student_recognition_id' => $recognition->id,
                'approval_status' => $recognition->status === 'published' ? 'approved' : 'pending',
                'submitted_by' => $recognition->created_by,
                'observed_by' => $recognition->created_by,
                'approved_by' => $recognition->published_by,
                'approved_at' => $recognition->published_at,
                'eligible_timeline' => true,
                'eligible_portfolio' => false,
                'eligible_celebration' => true,
                'eligible_digest' => true,
                'eligible_display' => (bool) $recognition->public_display_approved,
                'eligible_house_points' => false,
                'eligible_memory_book' => true,
                'created_at' => $recognition->created_at,
                'updated_at' => $recognition->updated_at,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_portfolio_versions');
        Schema::dropIfExists('student_portfolio_items');
        Schema::dropIfExists('student_portfolio_settings');
        Schema::dropIfExists('student_activity_record_media');
        Schema::dropIfExists('student_activity_records');
        Schema::table('parent_records', fn (Blueprint $table) => $table->dropColumn('can_view_growth_timeline'));
    }
};
