<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSIONS = [
        'read exam record',
        'create exam record',
        'check result',
    ];

    public function up(): void
    {
        DB::table('permissions')->whereIn('name', self::PERMISSIONS)->delete();
    }

    public function down(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission, 'guard_name' => 'web'],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }
};
