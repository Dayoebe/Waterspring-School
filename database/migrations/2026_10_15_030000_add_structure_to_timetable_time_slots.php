<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timetable_time_slots', function (Blueprint $table) {
            $table->string('label', 80)->nullable()->after('timetable_id');
            $table->boolean('is_break')->default(false)->after('label');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('is_break');
        });
    }

    public function down(): void
    {
        Schema::table('timetable_time_slots', function (Blueprint $table) {
            $table->dropColumn(['label', 'is_break', 'sort_order']);
        });
    }
};
