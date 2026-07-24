<?php

namespace App\Domain\Social\Actions;

use App\Models\MarketingCampaignPost;
use App\Models\User;
use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\Social\MarketingCampaignPostCommentType;
use App\Enums\Social\MarketingCampaignPostCommentVisibility;
use App\Jobs\RequestMarketingCampaignPostRegenerationJob;
use App\Domain\Social\Builders\MarketingCampaignPostMediaPayloadBuilder;
use Illuminate\Support\Str;
use Exception;

class RequestMarketingCampaignPostRegenerationAction
{
    public function __construct(
        private readonly \App\Domain\Social\Builders\MarketingCampaignPostMediaPayloadBuilder $builder
    ) {}

    public function execute(MarketingCampaignPost $post, User $user, string $regenerationType, ?string $prompt = null): void
    {
        $jobData = \Illuminate\Support\Facades\DB::transaction(function () use (&$post, $user, $regenerationType, $prompt) {
            $post = MarketingCampaignPost::lockForUpdate()->findOrFail($post->id);

            if (!$post->canRegenerate()) {
                throw new Exception("Non è possibile rigenerare un post in stato: {$post->status->label()}");
            }

            $requestId = 'cmp_regen_' . Str::uuid()->toString();

            $post->loadMissing(['campaign.client', 'currentVersion']);

            $campaign = $post->campaign;
            $client = $campaign->client;
            $currentVersion = $post->currentVersion;
            $previousStatus = $post->status->value ?? $post->status;

            // Costruisci il payload PRIMA di creare il commento e alterare il DB
            $mediaPayload = $this->builder->build($post);
            $resolvedUrls = array_column($mediaPayload['media_items'], 'url');

            // Salva il commento interno
            $post->comments()->create([
                'marketing_campaign_post_version_id' => $post->current_version_id,
                'user_id' => $user->id,
                'body' => $prompt ?? "Richiesta di rigenerazione ($regenerationType)",
                'visibility' => MarketingCampaignPostCommentVisibility::Internal->value,
                'type' => MarketingCampaignPostCommentType::ChangeRequest->value,
            ]);

            $payload = [
                'type' => 'marketing_campaign_post_regeneration',
                'post_id' => $post->id,
                'request_id' => $requestId,
                'regeneration_type' => $regenerationType,
                'prompt' => $prompt,

                'campaign' => [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                ],

                'client' => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'logo_url' => $client->logo_url,
                    'activity_description' => $client->activity_description,
                ],

                'post' => array_merge([
                    'id' => $post->id,
                    'title' => $post->title,
                    'description' => $post->description,
                    'content_type' => $post->content_type->value ?? $post->content_type,
                    'publishing_platforms' => $post->publishing_platforms ?? [],
                ], $mediaPayload),

                'current_version' => $currentVersion ? [
                    'id' => $currentVersion->id,
                    'version_number' => $currentVersion->version_number,
                    'title' => $currentVersion->title,
                    'caption' => $currentVersion->caption,
                    'hashtags' => $currentVersion->hashtags,
                    'image_url' => $resolvedUrls[0] ?? null,
                    'image_urls' => $resolvedUrls,
                ] : null,

                'callback_url' => route('api.v1.integrations.n8n.marketing-campaign-posts.versions.store', $post),
                'failed_callback_url' => route('api.v1.integrations.n8n.marketing-campaign-posts.failed', $post),
            ];

            $post->update([
                'n8n_previous_status' => $previousStatus,
                'status' => MarketingCampaignPostStatus::Regenerating->value,
                'n8n_request_id' => $requestId,
                'n8n_error' => null,
                'submitted_to_n8n_at' => null,
                'n8n_completed_at' => null,
                'approved_payload_snapshot' => $payload,
                'n8n_payload_hash' => hash('sha256', json_encode($payload)),
            ]);

            return [
                'payload' => $payload,
                'previousStatus' => $previousStatus
            ];
        });

        try {
            RequestMarketingCampaignPostRegenerationJob::dispatch(
                $post,
                $jobData['payload'],
                $jobData['previousStatus']
            );
        } catch (\Throwable $exception) {
            $post->refresh();
            if ($post->n8n_request_id === $jobData['payload']['request_id']) {
                $post->update([
                    'status' => $jobData['previousStatus'],
                    'n8n_request_id' => null,
                    'n8n_payload_hash' => null,
                    'approved_payload_snapshot' => null,
                ]);

                $post->comments()
                    ->where('user_id', $user->id)
                    ->where('type', MarketingCampaignPostCommentType::ChangeRequest->value)
                    ->where('body', $prompt ?? "Richiesta di rigenerazione ($regenerationType)")
                    ->latest()
                    ->first()
                    ?->delete();
            }
            throw $exception;
        }
    }
}
