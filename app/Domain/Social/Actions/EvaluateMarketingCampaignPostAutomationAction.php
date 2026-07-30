<?php

namespace App\Domain\Social\Actions;

use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\Social\PublicationMode;
use App\Models\MarketingCampaignPost;
use Illuminate\Support\Facades\DB;

class EvaluateMarketingCampaignPostAutomationAction
{
    public function __construct(
        private readonly SendMarketingCampaignPostToClientAction $sendToClient
    ) {}

    public function execute(MarketingCampaignPost $post): string
    {
        $post->loadMissing('campaign');

        if ($post->campaign->publication_mode !== PublicationMode::Automatic) {
            return 'manual_noop';
        }

        if ($post->campaign->client_review_required) {
            if ($post->status === MarketingCampaignPostStatus::SentToClient) {
                return 'review_already_requested';
            }

            if (! in_array($post->status, [
                MarketingCampaignPostStatus::Generated,
                MarketingCampaignPostStatus::ReadyForClient,
                MarketingCampaignPostStatus::ClientChangesRequested,
            ], true)) {
                return 'state_noop';
            }

            $this->sendToClient->execute($post);

            return 'review_requested';
        }

        return DB::transaction(function () use ($post): string {
            $locked = MarketingCampaignPost::query()
                ->with('campaign')
                ->whereKey($post->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $locked->campaign->publication_mode !== PublicationMode::Automatic
                || $locked->campaign->client_review_required
            ) {
                return 'configuration_changed';
            }

            if (in_array($locked->status, [
                MarketingCampaignPostStatus::Approved,
                MarketingCampaignPostStatus::ClientApproved,
            ], true)) {
                return 'already_approved';
            }

            if ($locked->status !== MarketingCampaignPostStatus::Generated) {
                return 'state_noop';
            }

            $locked->update([
                'status' => MarketingCampaignPostStatus::Approved,
            ]);

            return 'auto_approved';
        });
    }
}
