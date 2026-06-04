<?php

namespace App\Domain\Social\Actions;

use App\Models\MarketingCampaignPostPublication;
use App\Enums\Social\SocialPlatform;
use App\Enums\Social\PublicationStatus;
use App\Jobs\Social\TikTok\CheckTikTokPostStatusJob;

class RefreshPublicationStatusAction
{
    /**
     * Esegue il refresh dello stato di una singola pubblicazione
     * agnosticamente in base alla piattaforma.
     */
    public function execute(MarketingCampaignPostPublication $publication): void
    {
        if ($publication->status === PublicationStatus::Published || 
            $publication->status === PublicationStatus::Failed ||
            $publication->status === PublicationStatus::Cancelled) {
            // Stati terminali, non ha senso fare polling
            return;
        }

        if ($publication->platform === SocialPlatform::Tiktok) {
            if ($publication->status === PublicationStatus::Publishing && !empty($publication->external_container_id)) {
                $publication->increment('poll_count');
                CheckTikTokPostStatusJob::dispatch($publication->id);
            }
            return;
        }

        if ($publication->platform === SocialPlatform::Instagram && !empty($publication->external_container_id)) {
            $publication->increment('poll_count');
            app(\App\Domain\Social\Actions\ProcessInstagramContainerAction::class)->execute($publication);
            return;
        }
    }
}
