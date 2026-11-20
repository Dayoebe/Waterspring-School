<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->morphs('subject');
            $table->string('workflow', 60);
            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('submission_version')->default(1);
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status', 'submitted_at']);
            $table->index(['subject_type', 'subject_id', 'workflow', 'submission_version'], 'content_reviews_subject_workflow_version_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_reviews');
    }
};
