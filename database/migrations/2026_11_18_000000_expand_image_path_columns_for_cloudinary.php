<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const IMAGE_PATH_COLUMNS = [
        'schools' => ['logo_path'],
        'media_assets' => ['path'],
        'questions' => ['question_media_path'],
        'exam_papers' => ['attachment_path'],
        'notices' => ['attachment'],
        'assignments' => ['attachment_path'],
        'assignment_submissions' => ['attachment_path'],
        'admission_documents' => ['path'],
    ];

    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->string('logo_path', 2048)->nullable()->change();
        });
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->string('path', 2048)->change();
        });
        Schema::table('questions', function (Blueprint $table): void {
            $table->string('question_media_path', 2048)->nullable()->change();
        });
        Schema::table('exam_papers', function (Blueprint $table): void {
            $table->string('attachment_path', 2048)->nullable()->change();
        });
        Schema::table('notices', function (Blueprint $table): void {
            $table->string('attachment', 2048)->nullable()->change();
        });
        Schema::table('assignments', function (Blueprint $table): void {
            $table->string('attachment_path', 2048)->nullable()->change();
        });
        Schema::table('assignment_submissions', function (Blueprint $table): void {
            $table->string('attachment_path', 2048)->nullable()->change();
        });
        Schema::table('admission_documents', function (Blueprint $table): void {
            $table->string('path', 2048)->change();
        });
    }

    public function down(): void
    {
        foreach (self::IMAGE_PATH_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                if (DB::table($table)->whereRaw('CHAR_LENGTH(`' . $column . '`) > 100')->exists()) {
                    throw new RuntimeException('Cannot shrink ' . $table . '.' . $column . ' while Cloudinary URLs are stored there.');
                }
            }
        }

        Schema::table('schools', function (Blueprint $table): void {
            $table->string('logo_path', 100)->nullable()->change();
        });
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->string('path', 100)->change();
        });
        Schema::table('questions', function (Blueprint $table): void {
            $table->string('question_media_path', 100)->nullable()->change();
        });
        Schema::table('exam_papers', function (Blueprint $table): void {
            $table->string('attachment_path', 100)->nullable()->change();
        });
        Schema::table('notices', function (Blueprint $table): void {
            $table->string('attachment', 100)->nullable()->change();
        });
        Schema::table('assignments', function (Blueprint $table): void {
            $table->string('attachment_path', 100)->nullable()->change();
        });
        Schema::table('assignment_submissions', function (Blueprint $table): void {
            $table->string('attachment_path', 100)->nullable()->change();
        });
        Schema::table('admission_documents', function (Blueprint $table): void {
            $table->string('path', 100)->change();
        });
    }
};
