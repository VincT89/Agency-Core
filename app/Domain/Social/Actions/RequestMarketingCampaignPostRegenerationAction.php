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
    public function __construct()
    {
    }

    public function execute(MarketingCampaignPost $post, User $user, string $regenerationType, ?string $prompt = null): void
    {
        if (!$post->canRegenerate()) {
            throw new Exception("Non è possibile rigenerare un post in stato: {$post->status->label()}");
        }

        $requestId = 'cmp_regen_' . Str::uuid()->toString();

        // Salva il commento interno
        $post->comments()->create([
            'marketing_campaign_post_version_id' => $post->current_version_id,
            'user_id' => $user->id,
            'body' => $prompt ?? "Richiesta di rigenerazione ($regenerationType)",
            'visibility' => MarketingCampaignPostCommentVisibility::Internal->value,
            'type' => MarketingCampaignPostCommentType::ChangeRequest->value,
        ]);

        $post->loadMissing(['campaign.client', 'currentVersion']);

        $campaign = $post->campaign;
        $client = $campaign->client;
        $currentVersion = $post->currentVersion;
        $previousStatus = $post->status->value ?? $post->status;

        $mediaPayload = MarketingCampaignPostMediaPayloadBuilder::build($post);

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
                'image_url' => $currentVersion->image_url,
            ] : null,

            'callback_url' => route('api.v1.integrations.n8n.marketing-campaign-posts.versions.store', $post),
        ];

        $post->update([
            'n8n_previous_status' => $previousStatus,
            'status' => MarketingCampaignPostStatus::Regenerating->value,
            'n8n_request_id' => $requestId,
            'n8n_error' => null,
            'n8n_payload' => $payload,
        ]);

        RequestMarketingCampaignPostRegenerationJob::dispatch(
            $post,
            $payload,
            $previousStatus
        );
    }
}
