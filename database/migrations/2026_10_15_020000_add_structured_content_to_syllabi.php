<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE syllabi MODIFY file VARCHAR(255) NULL');

        Schema::table('syllabi', function (Blueprint $table): void {
            $table->foreignId('academic_year_id')->nullable()->after('semester_id')->constrained()->nullOnDelete();
            $table->foreignId('my_class_id')->nullable()->after('academic_year_id')->constrained('my_classes')->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->after('my_class_id')->constrained('users')->nullOnDelete();
            $table->text('overview')->nullable()->after('description');
            $table->text('aims')->nullable()->after('overview');
            $table->text('learning_outcomes')->nullable()->after('aims');
            $table->longText('scope_and_sequence')->nullable()->after('learning_outcomes');
            $table->text('prerequisites')->nullable()->after('scope_and_sequence');
            $table->text('teaching_methodology')->nullable()->after('prerequisites');
            $table->text('assessment_plan')->nullable()->after('teaching_methodology');
            $table->text('learning_resources')->nullable()->after('assessment_plan');
            $table->text('differentiation')->nullable()->after('learning_resources');
            $table->text('cross_curricular_links')->nullable()->after('differentiation');
            $table->text('homework_policy')->nullable()->after('cross_curricular_links');
            $table->text('references')->nullable()->after('homework_policy');
            $table->boolean('is_published')->default(false)->after('references');
            $table->timestamp('published_at')->nullable()->after('is_published');

            $table->index(['academic_year_id', 'semester_id', 'my_class_id'], 'syllabi_period_class_index');
        });
    }

    public function down(): void
    {
        Schema::table('syllabi', function (Blueprint $table): void {
            $table->dropIndex('syllabi_period_class_index');
            $table->dropConstrainedForeignId('teacher_id');
            $table->dropConstrainedForeignId('my_class_id');
            $table->dropConstrainedForeignId('academic_year_id');
            $table->dropColumn([
                'overview', 'aims', 'learning_outcomes', 'scope_and_sequence', 'prerequisites',
                'teaching_methodology', 'assessment_plan', 'learning_resources', 'differentiation',
                'cross_curricular_links', 'homework_policy', 'references', 'is_published', 'published_at',
            ]);
        });

        DB::table('syllabi')->whereNull('file')->update(['file' => '']);
        DB::statement('ALTER TABLE syllabi MODIFY file VARCHAR(100) NOT NULL');
    }
};
