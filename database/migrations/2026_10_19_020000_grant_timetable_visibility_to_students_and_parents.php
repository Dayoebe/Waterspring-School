<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::findOrCreate('read timetable', 'web');

        foreach (['student', 'parent'] as $roleName) {
            Role::query()->where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (['student', 'parent'] as $roleName) {
            Role::query()->where('name', $roleName)->where('guard_name', 'web')->first()?->revokePermissionTo('read timetable');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
