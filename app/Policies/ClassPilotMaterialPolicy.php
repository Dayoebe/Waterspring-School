<?php

namespace App\Policies;

use App\Models\ClassPilot\Material;
use App\Models\User;

class ClassPilotMaterialPolicy
{
    public function view(User $user, Material $material): bool
    {
        if ($user->school_id !== $material->school_id) {
            return false;
        }
        if ($user->hasAnyRole(['principal', 'admin', 'super-admin', 'super_admin'])) {
            return true;
        }
        if ($user->hasRole('teacher')) {
            return $material->author_id === $user->id;
        }

        return $material->status === 'approved' && $this->studentIsInClass($user, $material);
    }

    public function update(User $user, Material $material): bool
    {
        return $user->school_id === $material->school_id && $material->author_id === $user->id && $material->status !== 'approved';
    }

    public function approve(User $user, Material $material): bool
    {
        return $user->school_id === $material->school_id && $user->can('approve classpilot material') && $material->author_id === $user->id;
    }

    private function studentIsInClass(User $user, Material $material): bool
    {
        if (! $user->hasRole('student') || ! $user->studentRecord) {
            return false;
        }

        return (int) $user->studentRecord->my_class_id === (int) $material->my_class_id;
    }
}
