<?php

namespace App\Domain\Social\Actions;

use App\Models\MarketingCampaignPost;
use App\Models\User;
use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\Social\MarketingCampaignPostCommentType;
use App\Enums\Social\MarketingCampaignPostCommentVisibility;
use App\Services\Integrations\N8n\N8nClient;
use Illuminate\Support\Str;
use Exception;

class RequestMarketingCampaignPostRegenerationAction
{
    public function __construct(private N8nClient $n8nClient)
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

        $post->update([
            'status' => MarketingCampaignPostStatus::Regenerating->value,
            'n8n_request_id' => $requestId,
        ]);

        $currentVersion = $post->currentVersion;

        $payload = [
            'type' => 'marketing_campaign_post_regeneration',
            'post_id' => $post->id,
            'request_id' => $requestId,
            'regeneration_type' => $regenerationType,
            'prompt' => $prompt,
            'current_version' => $currentVersion ? [
                'version_number' => $currentVersion->version_number,
                'title' => $currentVersion->title,
                'caption' => $currentVersion->caption,
                'hashtags' => $currentVersion->hashtags,
                'image_url' => $currentVersion->image_url,
            ] : null,
            'callback_url' => route('api.v1.integrations.n8n.marketing-campaign-posts.versions.store', $post),
        ];

        // Se vogliamo farlo in background possiamo creare un Job, per ora chiamiamo N8n
        $this->n8nClient->requestMarketingCampaignPostRegeneration($payload);
    }
}
