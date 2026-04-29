<?php

namespace App\Policies;

use App\Models\Shooting\Shoot;
use App\Models\User;
use App\Policies\Concerns\HandlesRoleAuthorization;

class ShootPolicy
{
    use HandlesRoleAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->isOperationalStaff();
    }

    public function view(User $user, Shoot $shoot): bool
    {
        return $this->canAccessShoot($user, $shoot);
    }

    public function create(User $user): bool
    {
        return $user->isMarketing() || $user->isDeveloper();
    }

    public function update(User $user, Shoot $shoot): bool
    {
        // Consenti aggiornamento al team interno o al fotografo assegnato
        if ($user->isMarketing() || $user->isDeveloper()) {
            return $this->canAccessShoot($user, $shoot);
        }
        
        if ($user->isPhotographer() && $shoot->photographer_id === $user->id) {
            return true;
        }

        return false;
    }

    public function delete(User $user, Shoot $shoot): bool
    {
        return false; // Autorizzazione gestita dal metodo before()
    }

    private function canAccessShoot(User $user, Shoot $shoot): bool
    {
        if ($user->canBypassProjectScope()) {
            return true;
        }

        if ($user->isPhotographer()) {
            return $shoot->photographer_id === $user->id;
        }

        return $shoot->project_id && $user->projects()->where('projects.id', $shoot->project_id)->exists();
    }

    public function respond(User $user, Shoot $shoot): bool
    {
        return $user->isPhotographer() && $shoot->photographer_id === $user->id;
    }

    public function confirmClient(User $user, Shoot $shoot): bool
    {
        // Autorizzazione ristretta agli Admin (gestita dal metodo before)
        return false;
    }
}
