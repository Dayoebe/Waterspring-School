<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_recognitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('my_class_id')->constrained('my_classes')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->string('category', 80);
            $table->string('custom_category')->nullable();
            $table->text('reason');
            $table->string('congratulatory_message', 500);
            $table->date('week_start_date');
            $table->date('week_end_date');
            $table->text('photo_path')->nullable();
            $table->string('status', 20)->default('draft');
            $table->boolean('public_display_approved')->default(false);
            $table->unsignedTinyInteger('published_slot')->nullable();
            $table->timestamp('custom_category_approved_at')->nullable();
            $table->foreignId('custom_category_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'status', 'week_start_date']);
            $table->index(['student_id', 'status', 'published_at']);
            $table->unique(
                ['school_id', 'my_class_id', 'week_start_date', 'published_slot'],
                'recognitions_one_published_per_class_week'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_recognitions');
    }
};
