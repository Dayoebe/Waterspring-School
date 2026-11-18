<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_recognitions', function (Blueprint $table) {
            $table->foreignId('semester_id')
                ->nullable()
                ->after('academic_year_id')
                ->constrained()
                ->restrictOnDelete();

            $table->index(['school_id', 'academic_year_id', 'semester_id'], 'recognitions_academic_context_index');
        });
    }

    public function down(): void
    {
        Schema::table('student_recognitions', function (Blueprint $table) {
            $table->dropIndex('recognitions_academic_context_index');
            $table->dropConstrainedForeignId('semester_id');
        });
    }
};
