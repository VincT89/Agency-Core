<?php

namespace App\Policies;

use App\Models\MarketingCampaign;
use App\Models\User;

class MarketingCampaignPolicy
{
    use \App\Policies\Concerns\HandlesRoleAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->isMarketing();
    }

    public function view(User $user, MarketingCampaign $marketingCampaign): bool
    {
        return $user->isMarketing();
    }

    public function create(User $user): bool
    {
        return $user->isMarketing();
    }

    public function update(User $user, MarketingCampaign $marketingCampaign): bool
    {
        return $user->isMarketing();
    }

    public function delete(User $user, MarketingCampaign $marketingCampaign): bool
    {
        return $user->isMarketing();
    }
}
