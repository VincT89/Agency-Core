<?php

namespace App\Domain\Social\Actions;

use App\Domain\Social\Exceptions\MarketingCampaignPostArchiveException;
use App\Models\MarketingCampaignPost;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ArchiveMarketingCampaignPostAction
{
    public function execute(MarketingCampaignPost $post, User $actor): MarketingCampaignPost
    {
        return DB::transaction(function () use ($post, $actor) {
            $lockedPost = MarketingCampaignPost::query()
                ->whereKey($post->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPost->isArchived()) {
                return $lockedPost;
            }

            $publications = $lockedPost->publications()
                ->lockForUpdate()
                ->get();
            $lockedPost->setRelation('publications', $publications);

            if (! $lockedPost->canBeArchived()) {
                throw MarketingCampaignPostArchiveException::notAllowed($lockedPost);
            }

            $lockedPost->forceFill([
                'archived_at' => now(),
                'archived_by' => $actor->getKey(),
            ])->save();

            return $lockedPost->refresh();
        });
    }
}
