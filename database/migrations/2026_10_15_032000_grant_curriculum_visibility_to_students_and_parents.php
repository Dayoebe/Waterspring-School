<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = Permission::query()
            ->whereIn('name', ['read timetable', 'read syllabus', 'view schemes of work'])
            ->get();

        foreach (['student', 'parent'] as $roleName) {
            if ($role = Role::query()->where('name', $roleName)->first()) {
                $role->givePermissionTo($permissions);
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (['student', 'parent'] as $roleName) {
            if ($role = Role::query()->where('name', $roleName)->first()) {
                foreach (['read timetable', 'read syllabus'] as $permission) {
                    if (Permission::query()->where('name', $permission)->exists()) {
                        $role->revokePermissionTo($permission);
                    }
                }
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
