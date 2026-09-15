<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parent_records', function (Blueprint $table) {
            $table->unique('student_id', 'parent_records_student_unique');
        });
    }

    public function down(): void
    {
        Schema::table('parent_records', function (Blueprint $table) {
            $table->dropUnique('parent_records_student_unique');
        });
    }
};
