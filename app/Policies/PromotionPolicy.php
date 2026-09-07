<?php

namespace App\Policies;

use App\Models\Promotion;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PromotionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the given user can view the promotion.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('read promotion');
    }

    /**
     * Determine if the given user can create promotions.
     */
    public function promote(User $user): bool
    {
        return $user->can('promote student');
    }

    /**
     * Determine if the given user can reset promotion.
     */
    public function reset(User $user): bool
    {
        return $user->can('reset promotion');
    }

    /**
     * Determine if the given user can view the promotion.
     */
    public function view(User $user, Promotion $promotion): bool
    {
        return $user->can('read promotion') && $promotion->school_id === $user->school_id;
    }
}
