<?php

namespace App\Domain\Social\Actions;

use App\Models\MarketingCampaignPost;
use Illuminate\Support\Facades\DB;

class RestoreMarketingCampaignPostAction
{
    public function execute(MarketingCampaignPost $post): MarketingCampaignPost
    {
        return DB::transaction(function () use ($post) {
            $lockedPost = MarketingCampaignPost::query()
                ->whereKey($post->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedPost->isArchived()) {
                return $lockedPost;
            }

            $lockedPost->forceFill([
                'archived_at' => null,
                'archived_by' => null,
            ])->save();

            return $lockedPost->refresh();
        });
    }
}
