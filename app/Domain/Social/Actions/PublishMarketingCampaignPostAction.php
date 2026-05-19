<?php

namespace App\Domain\Social\Actions;

use App\Models\MarketingCampaignPost;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaignPostPublication;
use App\Domain\Social\Publishing\SocialPublisherInterface;
use App\Domain\Social\Publishing\MetaPublisher;
use App\Enums\Social\SocialPlatform;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PublishMarketingCampaignPostAction
{
    public function execute(MarketingCampaignPost $post, string $platform, ?string $correlationId = null): MarketingCampaignPostPublication
    {
        $correlationId = $correlationId ?? Str::uuid()->toString();
        $client = $post->campaign->client;
        $account = $client->socialAccountFor($platform);

        $lock = \Illuminate\Support\Facades\Cache::lock("publish_post_{$post->id}_{$platform}", 10);
        
        if (!$lock->get()) {
            throw new \Exception("Pubblicazione già in corso per il post {$post->id} su {$platform}");
        }

        try {
            // Idempotency check: prevent duplicate publications
            $existingPublication = MarketingCampaignPostPublication::where('marketing_campaign_post_id', $post->id)
                ->where('platform', $platform)
                ->whereIn('status', ['published', 'publishing'])
                ->first();

            if ($existingPublication) {
                Log::info("Idempotency check: Post {$post->id} is already {$existingPublication->status} on {$platform}.");
                return $existingPublication;
            }

            // Creiamo subito il record di pubblicazione in stato pending
            $publication = MarketingCampaignPostPublication::create([
                'marketing_campaign_post_id' => $post->id,
                'client_social_account_id' => $account?->id,
                'platform' => $platform,
                'status' => 'pending',
                'correlation_id' => $correlationId,
            ]);
            
            $publication->update(['status' => 'publishing']);
        } finally {
            $lock->release();
        }

        if (!$account) {
            $this->failPublication($publication, "Nessun account social trovato per la piattaforma {$platform}");
            return $publication;
        }

        $publisher = $this->resolvePublisher($platform);
        
        if (!$publisher) {
            $this->failPublication($publication, "Nessun publisher supportato per la piattaforma {$platform}");
            return $publication;
        }

        if (!$publisher->verifyConfiguration($account)) {
            $this->failPublication($publication, "Account non configurato per la pubblicazione API.");
            return $publication;
        }

        $result = $publisher->publish($post, $account, $correlationId);

        if ($result->success) {
            $updateData = [
                'external_post_id' => $result->externalPostId,
                'external_container_id' => $result->externalContainerId,
                'external_permalink' => $result->externalPermalink,
                'response_snapshot' => $result->responseSnapshot,
                'provider_last_response' => $result->responseSnapshot,
            ];

            // Gestione stato parziale per Instagram Container (reconciliation required)
            if ($result->isProcessing()) {
                $updateData['status'] = 'publishing';
                $updateData['meta_processing_state'] = 'IN_PROGRESS';
                $publication->update($updateData);
                
                \App\Jobs\Social\CheckInstagramContainerStatusJob::dispatch($publication)
                    ->delay(now()->addSeconds(15));
            } else {
                $updateData['status'] = 'published';
                $updateData['published_at'] = now();
                $publication->update($updateData);
            }
        } else {
            $this->failPublication($publication, $result->errorMessage, $result->responseSnapshot);
        }

        return $publication;
    }

    private function resolvePublisher(string $platform): ?SocialPublisherInterface
    {
        if (in_array($platform, [SocialPlatform::Facebook->value, SocialPlatform::Instagram->value])) {
            return app(MetaPublisher::class); // Uso l'app() per iniettare il mediaUrlService
        }
        return null;
    }

    private function failPublication(MarketingCampaignPostPublication $publication, string $error, ?array $response = null): void
    {
        $publication->update([
            'status' => 'failed',
            'error_message' => $error,
            'response_snapshot' => $response,
            'provider_last_response' => $response,
        ]);
        
        Log::error("Post Publication Failed", [
            'publication_id' => $publication->id, 
            'error' => $error,
            'correlation_id' => $publication->correlation_id
        ]);
    }
}
