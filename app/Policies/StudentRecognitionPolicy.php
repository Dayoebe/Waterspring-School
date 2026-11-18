<?php

namespace App\Policies;

use App\Models\StudentRecognition;
use App\Models\User;

class StudentRecognitionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('read student recognition');
    }

    public function create(User $user): bool
    {
        return $user->can('create student recognition');
    }

    public function update(User $user, StudentRecognition $recognition): bool
    {
        return $recognition->school_id === $user->school_id
            && $user->can('update student recognition')
            && ($user->hasAnyRole(['principal', 'admin', 'super-admin', 'super_admin']) || $recognition->myClass()->whereHas('teachers', fn ($q) => $q->whereKey($user->id))->exists());
    }

    public function publish(User $user, StudentRecognition $recognition): bool
    {
        return $recognition->school_id === $user->school_id
            && $user->can('publish student recognition')
            && ($user->hasAnyRole(['principal', 'admin', 'super-admin', 'super_admin']) || $recognition->myClass()->whereHas('teachers', fn ($q) => $q->whereKey($user->id))->exists());
    }

    public function delete(User $user, StudentRecognition $recognition): bool
    {
        return $recognition->status !== 'published' && $this->update($user, $recognition);
    }
}
