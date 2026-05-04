<?php

namespace App\Policies;

use App\Models\MarketingProject;
use App\Models\User;

class MarketingProjectPolicy
{
    use \App\Policies\Concerns\HandlesRoleAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->canManageSystem() 
            || $user->isMarketing() 
            || $user->projects()->exists();
    }

    public function view(User $user, MarketingProject $project): bool
    {
        if (! $project->project) {
            return false;
        }
        return $user->can('view', $project->project);
    }

    public function create(User $user): bool
    {
        return $user->isMarketing();
    }

    public function update(User $user, MarketingProject $project): bool
    {
        if (! $project->project) {
            return false;
        }
        return $user->can('update', $project->project);
    }

    public function delete(User $user, MarketingProject $project): bool
    {
        return false;
    }
}
