<?php

namespace App\Domain\Social\Actions;

use App\Models\MarketingCampaignPost;
use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\Social\PublicationStatus;

class SyncMarketingCampaignPostPublicationStatusAction
{
    public function execute(MarketingCampaignPost $post): void
    {
        $post->loadMissing('publications');
        $publications = $post->publications;

        $activePublications = $publications->filter(fn($p) => !in_array($p->status instanceof PublicationStatus ? $p->status->value : $p->status, [
            PublicationStatus::Abandoned->value,
            PublicationStatus::Superseded->value,
        ]));

        if ($activePublications->isEmpty()) {
            if ($post->status !== MarketingCampaignPostStatus::Approved) {
                $post->update(['status' => MarketingCampaignPostStatus::Approved]);
            }
            return;
        }

        $statuses = $activePublications->pluck('status')->map(fn($s) => $s instanceof PublicationStatus ? $s->value : $s)->toArray();

        $hasPublished = in_array(PublicationStatus::Published->value, $statuses);
        $hasFailed = in_array(PublicationStatus::Failed->value, $statuses);
        $hasNeedsManualReview = in_array(PublicationStatus::NeedsManualReview->value, $statuses);
        $hasPendingOrPublishing = in_array(PublicationStatus::Pending->value, $statuses) || in_array(PublicationStatus::Publishing->value, $statuses);

        $allPublished = count(array_unique($statuses)) === 1 && $statuses[0] === PublicationStatus::Published->value;
        $allTerminalFailure = collect($statuses)->every(fn($s) => in_array($s, [PublicationStatus::Failed->value, PublicationStatus::NeedsManualReview->value]));

        $newState = null;

        if ($allPublished) {
            $newState = MarketingCampaignPostStatus::Published;
        } elseif ($hasPendingOrPublishing) {
            // Include sia il caso (1 Published + 1 Publishing) sia (Nessuna Published + 1 Publishing)
            $newState = MarketingCampaignPostStatus::Publishing;
        } elseif ($hasPublished && ($hasFailed || $hasNeedsManualReview)) {
            $newState = MarketingCampaignPostStatus::PartialSuccess;
        } elseif ($hasNeedsManualReview && !$hasPublished) {
            $newState = MarketingCampaignPostStatus::NeedsManualReview;
        } elseif ($allTerminalFailure) {
            $newState = MarketingCampaignPostStatus::Failed;
        } else {
            // Fallback sicuro se gli stati non ricadono nelle casistiche previste (es. stati misti corrotti o nuovi)
            $newState = MarketingCampaignPostStatus::Failed;
            \Illuminate\Support\Facades\Log::warning("Combinazione di stati publication non gestita per post {$post->id}. Fallback a Failed.", ['statuses' => $statuses]);
        }

        if ($newState && $post->status !== $newState) {
            $post->update(['status' => $newState]);
        }
    }
}
