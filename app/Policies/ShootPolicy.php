<?php

namespace App\Policies;

use App\Models\MarketingCampaign;
use App\Models\Shooting\Shoot;
use App\Models\User;

class ShootPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->canManageSystem() && $ability !== 'respond') {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isOperationalStaff() || $user->canBypassProjectScope();
    }

    public function view(User $user, Shoot $shoot): bool
    {
        return $this->canAccessShoot($user, $shoot);
    }

    public function create(User $user): bool
    {
        return $user->canManageMarketing();
    }

    public function update(User $user, Shoot $shoot): bool
    {
        return $user->canManageMarketing() && $this->canAccessShoot($user, $shoot);
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

        if ($user->isPhotographer() && $shoot->photographer_id === $user->id) {
            return true;
        }

        if ($shoot->project_id) {
            return $user->projects()->where('projects.id', $shoot->project_id)->exists();
        }

        if ($shoot->marketing_campaign_id) {
            return MarketingCampaign::query()
                ->visibleTo($user)
                ->whereKey($shoot->marketing_campaign_id)
                ->exists();
        }

        return false;
    }

    public function respond(User $user, Shoot $shoot): bool
    {
        return $user->isPhotographer() && $shoot->photographer_id === $user->id;
    }

    public function confirmClient(User $user, Shoot $shoot): bool
    {
        return $user->canManageMarketing() && $this->canAccessShoot($user, $shoot);
    }

    public function revise(User $user, Shoot $shoot): bool
    {
        return $user->canManageMarketing() && $this->canAccessShoot($user, $shoot);
    }
}
