<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserAvailability;

class UserAvailabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, UserAvailability $availability): bool
    {
        return $user->canManageSystem() || $availability->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, UserAvailability $availability): bool
    {
        return $availability->user_id === $user->id;
    }

    public function delete(User $user, UserAvailability $availability): bool
    {
        return $availability->user_id === $user->id;
    }
}
