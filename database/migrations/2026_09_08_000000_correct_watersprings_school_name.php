<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('schools')
            ->where('name', 'Waterspring International College')
            ->update([
                'name' => 'Watersprings International School Akure',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('schools')
            ->where('name', 'Watersprings International School Akure')
            ->update([
                'name' => 'Waterspring International College',
                'updated_at' => now(),
            ]);
    }
};
