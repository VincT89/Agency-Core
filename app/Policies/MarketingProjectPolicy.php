<?php

namespace App\Policies;

use App\Models\MarketingProject;
use App\Models\User;

class MarketingProjectPolicy
{
    use \App\Policies\Concerns\HandlesRoleAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->isMarketing();
    }

    public function view(User $user, MarketingProject $project): bool
    {
        return $user->isMarketing();
    }

    public function create(User $user): bool
    {
        return $user->isMarketing();
    }

    public function update(User $user, MarketingProject $project): bool
    {
        return $user->isMarketing();
    }

    public function delete(User $user, MarketingProject $project): bool
    {
        return false;
    }
}
