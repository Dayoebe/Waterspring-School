<?php

namespace App\Policies;

use App\Models\ClassPilot\LearningSession;
use App\Models\User;

class ClassPilotSessionPolicy
{
    public function view(User $user, LearningSession $session): bool
    {
        return $user->school_id === $session->school_id && $user->id === $session->user_id;
    }

    public function update(User $user, LearningSession $session): bool
    {
        return $this->view($user, $session);
    }
}
