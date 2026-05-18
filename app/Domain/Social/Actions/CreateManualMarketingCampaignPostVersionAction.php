<?php

namespace App\Domain\Social\Actions;

use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostVersion;
use App\Models\User;
use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\Social\MarketingCampaignPostVersionSource;
use App\Enums\Social\MarketingCampaignPostRegenerationType;
use App\Domain\Social\Builders\MarketingCampaignPostMediaPayloadBuilder;
use Illuminate\Support\Facades\DB;

class CreateManualMarketingCampaignPostVersionAction
{
    public function execute(MarketingCampaignPost $post, ?User $user = null): MarketingCampaignPostVersion
    {
        return DB::transaction(function () use ($post, $user) {
            $post = MarketingCampaignPost::lockForUpdate()
                ->with(['mediaItems', 'versions'])
                ->findOrFail($post->id);

            $nextVersionNumber = ((int) $post->versions()->max('version_number')) + 1;

            $mediaPayload = MarketingCampaignPostMediaPayloadBuilder::build($post);
            
            $imageUrls = collect($mediaPayload['media_items'] ?? [])
                ->pluck('url')
                ->filter()
                ->values()
                ->all();

            $version = $post->versions()->create([
                'created_by' => $user?->id,
                'version_number' => $nextVersionNumber,
                'regeneration_type' => MarketingCampaignPostRegenerationType::Manual,
                'title' => $post->title,
                'caption' => $post->description,
                'hashtags' => null,
                'image_url' => $imageUrls[0] ?? null,
                'image_urls' => $imageUrls,
                'source' => MarketingCampaignPostVersionSource::Manual,
                'raw_payload' => [
                    'source' => 'manual',
                    'created_from_post' => true,
                ],
            ]);

            $post->forceFill([
                'current_version_id' => $version->id,
                'status' => MarketingCampaignPostStatus::Generated,
                'generated_at' => now(),
            ])->save();

            return $version;
        });
    }
}
