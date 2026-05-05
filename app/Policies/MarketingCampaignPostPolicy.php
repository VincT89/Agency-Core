<?php

namespace App\Policies;

use App\Models\MarketingCampaignPost;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class MarketingCampaignPostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageSystem() || $user->isMarketing();
    }

    public function view(User $user, MarketingCampaignPost $post): bool
    {
        return Gate::check('view', $post->campaign);
    }

    public function create(User $user): bool
    {
        return $user->canManageSystem() || $user->isMarketing();
    }

    public function update(User $user, MarketingCampaignPost $post): bool
    {
        return Gate::check('update', $post->campaign);
    }

    public function delete(User $user, MarketingCampaignPost $post): bool
    {
        return Gate::check('update', $post->campaign);
    }
}
