<?php

namespace App\Policies;

use App\Models\OpenLearningRecord;
use App\Models\User;

class OpenLearningRecordPolicy
{
    public function view(User $user, OpenLearningRecord $record): bool
    {
        return (int) $record->user_id === (int) $user->id;
    }

    public function update(User $user, OpenLearningRecord $record): bool
    {
        return $this->view($user, $record);
    }

    public function delete(User $user, OpenLearningRecord $record): bool
    {
        return $this->view($user, $record);
    }
}
