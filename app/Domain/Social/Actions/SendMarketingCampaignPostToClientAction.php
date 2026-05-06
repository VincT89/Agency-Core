<?php

namespace App\Domain\Social\Actions;

use App\Models\MarketingCampaignPost;
use App\Models\ClientReviewToken;
use App\Enums\Social\MarketingCampaignPostStatus;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\Social\MarketingCampaignPostReviewMail;
use Exception;

class SendMarketingCampaignPostToClientAction
{
    public function execute(MarketingCampaignPost $post): ClientReviewToken
    {
        if (!in_array($post->status, [
            MarketingCampaignPostStatus::Generated,
            MarketingCampaignPostStatus::ReadyForClient,
            MarketingCampaignPostStatus::ClientChangesRequested
        ])) {
            throw new Exception("Impossibile inviare al cliente. Stato attuale: {$post->status->label()}");
        }

        if (!$post->currentVersion) {
            throw new Exception("Nessuna versione generata per questo post.");
        }

        $client = $post->campaign->client;

        // Non blocchiamo se manca la mail, generiamo comunque il link
        $hasEmail = !empty($client->email);

        $token = ClientReviewToken::create([
            'token' => Str::random(60),
            'reviewable_type' => MarketingCampaignPost::class,
            'reviewable_id' => $post->id,
            'expires_at' => now()->addDays(7),
            'metadata' => [
                'version_number' => $post->currentVersion->version_number,
            ]
        ]);

        $post->update([
            'status' => MarketingCampaignPostStatus::SentToClient->value,
            'sent_to_client_at' => now(),
        ]);

        if ($hasEmail) {
            Mail::to($client->email)->queue(new MarketingCampaignPostReviewMail($post, $token));
        }

        return $token;
    }
}
