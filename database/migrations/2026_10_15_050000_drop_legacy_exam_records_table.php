<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('exam_records');
    }

    public function down(): void
    {
        Schema::create('exam_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_slot_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('student_marks');
            $table->timestamps();
            $table->unique(['user_id', 'subject_id', 'section_id', 'exam_slot_id']);
        });
    }
};
