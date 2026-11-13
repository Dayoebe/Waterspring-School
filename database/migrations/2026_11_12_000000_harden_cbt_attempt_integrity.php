<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_answers', function (Blueprint $table): void {
            $table->foreignId('attempt_session_id')
                ->nullable()
                ->after('id')
                ->constrained('assessment_attempt_sessions')
                ->cascadeOnDelete();
        });

        DB::statement(<<<'SQL'
            UPDATE student_answers AS answers
            INNER JOIN assessment_attempt_sessions AS attempts
                ON attempts.user_id = answers.user_id
                AND attempts.assessment_id = answers.assessment_id
                AND attempts.attempt_number = answers.attempt_number
            SET answers.attempt_session_id = attempts.id
            WHERE answers.attempt_session_id IS NULL
        SQL);

        Schema::table('student_answers', function (Blueprint $table): void {
            $table->unique(
                ['attempt_session_id', 'question_id'],
                'student_answers_attempt_session_question_unique'
            );
        });

        Schema::table('assessment_attempt_sessions', function (Blueprint $table): void {
            $table->unsignedBigInteger('state_revision')->default(0)->after('last_activity_at');
        });
    }

    public function down(): void
    {
        Schema::table('student_answers', function (Blueprint $table): void {
            $table->dropUnique('student_answers_attempt_session_question_unique');
            $table->dropConstrainedForeignId('attempt_session_id');
        });

        Schema::table('assessment_attempt_sessions', function (Blueprint $table): void {
            $table->dropColumn('state_revision');
        });
    }
};
