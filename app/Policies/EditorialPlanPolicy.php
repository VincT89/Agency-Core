<?php

namespace App\Policies;

use App\Models\EditorialPlan;
use App\Models\User;

class EditorialPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isMarketing();
    }

    public function view(User $user, EditorialPlan $plan): bool
    {
        return $user->isAdmin() || $user->isMarketing();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isMarketing();
    }

    public function update(User $user, EditorialPlan $plan): bool
    {
        return $user->isAdmin() || $user->isMarketing();
    }

    public function delete(User $user, EditorialPlan $plan): bool
    {
        return $user->isAdmin();
    }
}
