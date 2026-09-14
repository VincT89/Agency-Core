<?php

namespace App\Policies;

use App\Models\MarketingCampaignPost;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class MarketingCampaignPostPolicy
{
    use \App\Policies\Concerns\HandlesRoleAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->isMarketing() || $user->isAdministration();
    }

    public function view(User $user, MarketingCampaignPost $post): bool
    {
        return Gate::forUser($user)->check('view', $post->campaign);
    }

    public function create(User $user): bool
    {
        return $user->isMarketing();
    }

    public function update(User $user, MarketingCampaignPost $post): bool
    {
        return Gate::forUser($user)->check('update', $post->campaign);
    }

    public function delete(User $user, MarketingCampaignPost $post): bool
    {
        return Gate::forUser($user)->check('update', $post->campaign);
    }
}
