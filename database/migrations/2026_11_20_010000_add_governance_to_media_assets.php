<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->string('original_name')->nullable()->after('alt_text');
            $table->string('visibility', 24)->default('public')->after('usage_area');
            $table->foreignId('subject_user_id')->nullable()->after('school_id')->constrained('users')->nullOnDelete();
            $table->string('checksum_sha256', 64)->nullable()->after('file_size');
            $table->timestamp('publication_approved_at')->nullable()->after('uploaded_by');
            $table->foreignId('publication_approved_by')->nullable()->after('publication_approved_at')->constrained('users')->nullOnDelete();

            $table->index(['school_id', 'visibility'], 'media_assets_school_visibility_index');
            $table->index(['subject_user_id', 'visibility'], 'media_assets_subject_visibility_index');
        });

        DB::table('media_assets')->update([
            'visibility' => 'public',
            'publication_approved_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)'),
            'publication_approved_by' => DB::raw('uploaded_by'),
        ]);
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropIndex('media_assets_school_visibility_index');
            $table->dropIndex('media_assets_subject_visibility_index');
            $table->dropForeign(['subject_user_id']);
            $table->dropForeign(['publication_approved_by']);
            $table->dropColumn([
                'subject_user_id',
                'visibility',
                'original_name',
                'checksum_sha256',
                'publication_approved_at',
                'publication_approved_by',
            ]);
        });
    }
};
