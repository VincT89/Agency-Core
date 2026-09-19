<?php

namespace App\Policies;

use App\Models\MarketingCampaign;
use App\Models\User;

class MarketingCampaignPolicy
{
    use \App\Policies\Concerns\HandlesRoleAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->canManageMarketing() || $user->isAdministration();
    }

    public function view(User $user, MarketingCampaign $marketingCampaign): bool
    {
        return $user->canManageMarketing() || $user->isAdministration();
    }

    public function create(User $user): bool
    {
        return $user->canManageMarketing();
    }

    public function update(User $user, MarketingCampaign $marketingCampaign): bool
    {
        return $user->canManageMarketing();
    }

    public function delete(User $user, MarketingCampaign $marketingCampaign): bool
    {
        return $user->canManageMarketing();
    }
}
