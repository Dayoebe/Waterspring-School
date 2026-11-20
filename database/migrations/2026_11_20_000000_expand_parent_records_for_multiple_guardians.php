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
            $table->dropUnique('parent_records_student_unique');

            $table->string('relationship_type', 40)->default('guardian')->after('student_id');
            $table->boolean('is_primary_contact')->default(false)->after('relationship_type');
            $table->boolean('receives_digest')->default(false)->after('is_primary_contact');
            $table->boolean('can_view_academics')->default(true)->after('receives_digest');
            $table->boolean('can_view_attendance')->default(true)->after('can_view_academics');
            $table->boolean('can_view_portfolio')->default(false)->after('can_view_attendance');
            $table->boolean('can_consent_events')->default(false)->after('can_view_portfolio');
            $table->date('active_from')->nullable()->after('can_consent_events');
            $table->date('active_until')->nullable()->after('active_from');
            $table->timestamp('verified_at')->nullable()->after('active_until');
            $table->foreignId('verified_by')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();

            $table->index(['student_id', 'is_primary_contact'], 'parent_records_student_primary_index');
            $table->index(['user_id', 'active_until'], 'parent_records_guardian_active_index');
        });

        DB::table('parent_records')->update([
            'is_primary_contact' => true,
            'verified_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)'),
        ]);
    }

    public function down(): void
    {
        $duplicateStudentLinks = DB::table('parent_records')
            ->select('student_id')
            ->groupBy('student_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicateStudentLinks) {
            throw new RuntimeException('Cannot restore the one-parent constraint while students have multiple guardian links.');
        }

        Schema::table('parent_records', function (Blueprint $table): void {
            $table->dropForeign(['verified_by']);
            $table->dropIndex('parent_records_student_primary_index');
            $table->dropIndex('parent_records_guardian_active_index');
            $table->dropColumn([
                'relationship_type', 'is_primary_contact', 'receives_digest',
                'can_view_academics', 'can_view_attendance', 'can_view_portfolio',
                'can_consent_events', 'active_from', 'active_until', 'verified_at', 'verified_by',
            ]);
            $table->unique('student_id', 'parent_records_student_unique');
        });
    }
};
