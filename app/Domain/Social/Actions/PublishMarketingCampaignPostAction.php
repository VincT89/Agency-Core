<?php

namespace App\Domain\Social\Actions;

use App\Models\MarketingCampaignPost;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaignPostPublication;
use App\Domain\Social\Publishing\SocialPublisherInterface;
use App\Domain\Social\Publishing\MetaPublisher;
use App\Enums\Social\SocialPlatform;
use App\Enums\Social\PublicationStatus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PublishMarketingCampaignPostAction
{
    public function execute(MarketingCampaignPost $post, string $platform, ?string $correlationId = null): MarketingCampaignPostPublication
    {
        $correlationId = $correlationId ?? Str::uuid()->toString();
        $client = $post->campaign->client;
        $account = $client->socialAccountFor($platform);

        $lock = \Illuminate\Support\Facades\Cache::lock("publish_post_{$post->id}_{$platform}", 120);
        
        if (!$lock->get()) {
            throw new \Exception("Pubblicazione già in corso per il post {$post->id} su {$platform}");
        }

        try {
            $transactionResult = \Illuminate\Support\Facades\DB::transaction(function () use ($post, $platform, $correlationId, $account) {
                // Idempotency check: prevent duplicate publications (con pessimistic lock)
                $existingPublication = MarketingCampaignPostPublication::where('marketing_campaign_post_id', $post->id)
                    ->where('platform', $platform)
                    ->latest('id')
                    ->lockForUpdate()
                    ->first();

                if ($existingPublication && in_array($existingPublication->status, [PublicationStatus::Pending, PublicationStatus::Published, PublicationStatus::Publishing])) {
                    
                    $timeoutMinutes = config('services.meta.instagram.max_container_lifecycle', 15);
                    // Stale Recovery Logic
                    if ($existingPublication->status === PublicationStatus::Publishing && $existingPublication->updated_at->diffInMinutes(now()) > $timeoutMinutes) {
                        if ($platform === SocialPlatform::Instagram->value) {
                            $existingPublication->update([
                                'status' => PublicationStatus::NeedsManualReview->value,
                                'error_message' => "Timeout: publication stuck in publishing for > {$timeoutMinutes}m. Async container might still be alive.",
                            ]);
                            Log::warning("Stale IG publication recovered for post {$post->id}. Marked as NeedsManualReview. Auto-retry blocked.");
                            return ['publication' => $existingPublication, 'action' => 'blocked'];
                        } else {
                            $existingPublication->update([
                                'status' => PublicationStatus::Failed->value,
                                'error_message' => "Timeout: synchronous publication stuck in publishing for > {$timeoutMinutes}m.",
                            ]);
                            Log::warning("Stale FB publication recovered for post {$post->id}. Marked as Failed. Allowing auto-retry.");
                            // Let it flow down to create a new pending publication
                        }
                    } else {
                        Log::info("Idempotency check: Post {$post->id} is already {$existingPublication->status->value} on {$platform}.");
                        return ['publication' => $existingPublication, 'action' => 'idempotency_hit'];
                    }
                }

                $publication = MarketingCampaignPostPublication::create([
                    'marketing_campaign_post_id' => $post->id,
                    'client_social_account_id' => $account?->id,
                    'platform' => $platform,
                    'status' => PublicationStatus::Pending->value,
                    'correlation_id' => $correlationId,
                ]);
                
                // Compare-and-swap status transition: atomico e sicuro
                $updated = MarketingCampaignPostPublication::where('id', $publication->id)
                    ->where('status', PublicationStatus::Pending->value)
                    ->update(['status' => PublicationStatus::Publishing->value]);
                    
                if (!$updated) {
                    throw new \Exception("Transizione di stato fallita per la pubblicazione {$publication->id}. Possibile race condition.");
                }
                
                return ['publication' => $publication->refresh(), 'action' => 'created'];
            });

            $publication = $transactionResult['publication'];

            if ($transactionResult['action'] !== 'created') {
                // Se è scattata l'idempotency o il blocco IG per stale, ritorniamo bypassing il publish effettivo
                return $publication;
            }



        if (!$account) {
            $this->failPublication($publication, "Nessun account social trovato per la piattaforma {$platform}");
            return $publication;
        }

        $publisher = $this->resolvePublisher($platform);
        
        if (!$publisher) {
            $this->failPublication($publication, "Nessun publisher supportato per la piattaforma {$platform}");
            app(\App\Domain\Social\Actions\SyncMarketingCampaignPostPublicationStatusAction::class)->execute($post);
            return $publication;
        }

        if (!$publisher->verifyConfiguration($account)) {
            $this->failPublication($publication, "Account non configurato per la pubblicazione API.");
            app(\App\Domain\Social\Actions\SyncMarketingCampaignPostPublicationStatusAction::class)->execute($post);
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
                $updateData['status'] = PublicationStatus::Publishing->value;
                $updateData['meta_processing_state'] = 'IN_PROGRESS';
                $publication->update($updateData);
                
                \App\Jobs\Social\CheckInstagramContainerStatusJob::dispatch($publication)
                    ->delay(now()->addSeconds(15));
            } else {
                $updateData['status'] = PublicationStatus::Published->value;
                $updateData['published_at'] = now();
                $publication->update($updateData);
            }
        } else {
            $this->failPublication($publication, $result->errorMessage, $result->responseSnapshot);
        }

        } finally {
            $lock->release();
            app(\App\Domain\Social\Actions\SyncMarketingCampaignPostPublicationStatusAction::class)->execute($post);
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
            'status' => PublicationStatus::Failed->value,
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
