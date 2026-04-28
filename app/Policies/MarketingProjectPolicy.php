<?php

namespace App\Policies;

use App\Models\MarketingProject;
use App\Models\User;

class MarketingProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isMarketing();
    }

    public function view(User $user, MarketingProject $project): bool
    {
        return $user->isAdmin() || $user->isMarketing();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isMarketing();
    }

    public function update(User $user, MarketingProject $project): bool
    {
        return $user->isAdmin() || $user->isMarketing();
    }

    public function delete(User $user, MarketingProject $project): bool
    {
        return $user->isAdmin();
    }
}
