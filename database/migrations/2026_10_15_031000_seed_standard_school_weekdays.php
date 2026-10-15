<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'] as $day) {
            if (!DB::table('weekdays')->where('name', $day)->exists()) {
                DB::table('weekdays')->insert([
                    'name' => $day,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Weekdays may already be referenced by saved timetables, so rollback keeps them intact.
    }
};
