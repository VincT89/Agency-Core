<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TeamPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        // Visibilità globale per la directory aziendale
        return true;
    }

    public function view(User $user, Team $team): bool
    {
        // Visibilità globale per la scheda team
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canManageSystem();
    }

    public function update(User $user, Team $team): bool
    {
        return $user->canManageSystem();
    }

    public function delete(User $user, Team $team): bool
    {
        return $user->canManageSystem();
    }
}
