<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->string('question_type');
            $table->text('prompt');
            $table->json('options')->nullable();
            $table->json('correct_answers')->nullable();
            $table->unsignedDecimal('points', 8, 2)->default(1);
            $table->text('explanation')->nullable();
            $table->unsignedInteger('position')->default(1);
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->index(['assignment_id', 'position']);
        });

        Schema::create('assignment_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignment_question_id')->constrained()->cascadeOnDelete();
            $table->json('answer')->nullable();
            $table->unsignedDecimal('score', 8, 2)->nullable();
            $table->boolean('is_correct')->nullable();
            $table->text('feedback')->nullable();
            $table->timestamps();

            $table->unique(['assignment_submission_id', 'assignment_question_id'], 'assignment_answer_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_answers');
        Schema::dropIfExists('assignment_questions');
    }
};
