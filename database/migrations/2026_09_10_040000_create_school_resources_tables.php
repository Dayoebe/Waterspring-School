<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_resources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('category')->nullable();
            $table->string('author_or_brand')->nullable();
            $table->string('location')->nullable();
            $table->string('condition', 30)->default('good');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('available_quantity')->default(1);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->date('acquired_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'code']);
            $table->index(['school_id', 'type', 'category']);
        });

        Schema::create('school_resource_loans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_resource_id')->constrained()->cascadeOnDelete();
            $table->foreignId('borrower_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->dateTime('issued_at');
            $table->dateTime('due_at')->nullable();
            $table->dateTime('returned_at')->nullable();
            $table->string('condition_on_return', 30)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_resource_id', 'returned_at']);
            $table->index(['borrower_id', 'returned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_resource_loans');
        Schema::dropIfExists('school_resources');
    }
};
