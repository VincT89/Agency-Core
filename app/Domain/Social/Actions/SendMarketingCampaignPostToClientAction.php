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
    public function __construct(
        private readonly \App\Domain\Social\Services\MarketingCampaignPostVersionMediaResolver $resolver,
        private readonly \App\Domain\Social\Services\MarketingCampaignPostMediaUrlResolver $urlResolver
    ) {}

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

        return \Illuminate\Support\Facades\DB::transaction(function () use ($post, $hasEmail, $client) {
            $post = MarketingCampaignPost::lockForUpdate()->findOrFail($post->id);
            
            if (!in_array($post->status, [
                MarketingCampaignPostStatus::Generated,
                MarketingCampaignPostStatus::ReadyForClient,
                MarketingCampaignPostStatus::ClientChangesRequested
            ])) {
                throw new Exception("Stato del post alterato durante l'operazione.");
            }

            $resolution = $this->resolver->resolveForPost($post);
            $previewUrls = $this->urlResolver->orderedDeliveryUrls($resolution->mediaItems);

            // Invalida eventuali token attivi precedenti per questo post
            ClientReviewToken::where('reviewable_type', MarketingCampaignPost::class)
                ->where('reviewable_id', $post->id)
                ->whereNull('used_at')
                ->update(['expires_at' => now()]);

            $token = ClientReviewToken::create([
                'token' => Str::random(60),
                'reviewable_type' => MarketingCampaignPost::class,
                'reviewable_id' => $post->id,
                'marketing_campaign_post_version_id' => $post->current_version_id,
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
                // Prepare mailable before afterCommit block to capture data
                $mailable = new MarketingCampaignPostReviewMail($post, $token, $previewUrls);
                
                \Illuminate\Support\Facades\DB::afterCommit(function () use ($client, $mailable) {
                    Mail::to($client->email)->queue($mailable);
                });
            }

            return $token;
        });
    }
}
