<?php

namespace App\Domain\Social\Actions;

use App\Domain\Social\DTOs\CreateManualMarketingCampaignPostVersionData;
use App\Domain\Social\DTOs\CreateManualMarketingCampaignPostVersionResult;
use App\Exceptions\Social\StaleMarketingCampaignPostVersionException;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostMedia;
use App\Models\MarketingCampaignPostVersion;
use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\Social\MarketingCampaignPostVersionSource;
use App\Enums\Social\MarketingCampaignPostRegenerationType;
use App\Domain\Social\Services\MarketingCampaignPostVersionMediaResolver;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateManualMarketingCampaignPostVersionAction
{
    public function execute(MarketingCampaignPost $post, CreateManualMarketingCampaignPostVersionData $data): CreateManualMarketingCampaignPostVersionResult
    {
        return DB::transaction(function () use ($post, $data) {
            $post = MarketingCampaignPost::lockForUpdate()
                ->with(['versions'])
                ->findOrFail($post->id);

            // Stale check
            if ($post->current_version_id !== $data->expected_current_version_id) {
                throw new StaleMarketingCampaignPostVersionException();
            }
            
            // Check valid status
            if (!$post->status->isManuallyEditable()) {
                throw new InvalidArgumentException("Stato non valido per creare una versione manuale.");
            }

            $currentVersion = $post->currentVersion;
            
            // Resolve source media via resolver
            $sourceMedia = collect();
            if ($currentVersion) {
                $resolver = app(MarketingCampaignPostVersionMediaResolver::class);
                $sourceMedia = $resolver->resolveMediaItems($currentVersion);
            } elseif ($post->mediaItems()->exists()) {
                $sourceMedia = $post->orderedMediaItems;
            }

            // Validate requested media
            $requestedMediaIds = array_values(array_unique($data->ordered_media_ids));

            // Blocker 10: Zero media not allowed if required
            if (empty($requestedMediaIds)) {
                // To keep it simple, we require at least 1 media for now.
                throw new InvalidArgumentException("È richiesto almeno un media per creare la versione.");
            }
            
            if (count($requestedMediaIds) !== count($data->ordered_media_ids)) {
                throw new InvalidArgumentException("ID media duplicati nella selezione.");
            }

            $validatedMedia = MarketingCampaignPostMedia::query()
                ->where('marketing_campaign_post_id', $post->id)
                ->whereIn('id', $requestedMediaIds)
                ->get();

            if ($validatedMedia->count() !== count($requestedMediaIds)) {
                throw new InvalidArgumentException("Uno o più media richiesti non esistono o non appartengono a questo post.");
            }

            // Order validated media as requested
            $orderedValidatedMedia = collect();
            foreach ($requestedMediaIds as $id) {
                $orderedValidatedMedia->push($validatedMedia->firstWhere('id', $id));
            }

            // Compare data (No-op check)
            $sourceTitle = $currentVersion ? $currentVersion->title : $post->title;
            $sourceCaption = $currentVersion ? $currentVersion->caption : $post->description;
            $sourceHashtags = $currentVersion ? $currentVersion->hashtags : null;
            $sourceMediaIds = $sourceMedia->pluck('id')->toArray();

            $titleChanged = $data->title !== $sourceTitle;
            $captionChanged = $data->caption !== $sourceCaption;
            $hashtagsChanged = $data->hashtags !== $sourceHashtags;
            $mediaChanged = $requestedMediaIds !== $sourceMediaIds;

            if (!$titleChanged && !$captionChanged && !$hashtagsChanged && !$mediaChanged && $currentVersion !== null) {
                return CreateManualMarketingCampaignPostVersionResult::unchanged($currentVersion);
            }

            // Compute values for new version (No fallback for title/caption to allow explicit nulls)
            $newTitle = $data->title;
            $newCaption = $data->caption;
            $newHashtags = $data->hashtags ?? $sourceHashtags;

            $nextVersionNumber = ((int) $post->versions()->max('version_number')) + 1;

            // Generate proper image_url, image_urls, and image_path
            $imageUrls = [];
            $imagePath = null;
            foreach ($orderedValidatedMedia as $media) {
                if ($media->source === 'nextcloud') {
                    $url = $media->nextcloud_share_url ? $media->nextcloud_share_url . '/download' : null;
                    if ($url) $imageUrls[] = $url;
                } else {
                    $url = \Illuminate\Support\Facades\Storage::disk('public')->url($media->path);
                    $imageUrls[] = $url;
                    
                    if ($imagePath === null) {
                        $imagePath = $media->path;
                    }
                }
            }

            $version = $post->versions()->create([
                'created_by' => $data->author_id,
                'version_number' => $nextVersionNumber,
                'regeneration_type' => MarketingCampaignPostRegenerationType::Manual,
                'title' => $newTitle,
                'caption' => $newCaption,
                'hashtags' => $newHashtags,
                'image_url' => $imageUrls[0] ?? null,
                'image_urls' => $imageUrls,
                'image_path' => $imagePath,
                'source' => MarketingCampaignPostVersionSource::Manual,
                'raw_payload' => [
                    'source' => 'manual',
                    'created_from_post' => true,
                    'notes' => $data->notes ?? null,
                ],
            ]);

            // Pivot with sort_order
            $pivotData = [];
            foreach ($orderedValidatedMedia as $index => $media) {
                $pivotData[$media->id] = ['sort_order' => $index];
            }
            if (!empty($pivotData)) {
                $version->mediaItems()->attach($pivotData);
            }

            // Dual Write Legacy Fields
            $firstMedia = $orderedValidatedMedia->first();
            $mediaUpdates = [
                'media_source' => null,
                'media_path' => null,
                'media_original_name' => null,
                'media_mime' => null,
                'nextcloud_path' => null,
                'nextcloud_share_url' => null,
                'nextcloud_file_id' => null,
            ];
            
            if ($firstMedia) {
                $mediaUpdates['media_original_name'] = $firstMedia->original_name;
                $mediaUpdates['media_mime'] = $firstMedia->mime_type;
                
                if (in_array($firstMedia->source, ['local', 'n8n'])) {
                    $mediaUpdates['media_source'] = $firstMedia->source;
                    $mediaUpdates['media_path'] = $firstMedia->path;
                } else if ($firstMedia->source === 'nextcloud') {
                    $mediaUpdates['media_source'] = 'nextcloud';
                    $mediaUpdates['nextcloud_path'] = $firstMedia->nextcloud_path;
                    $mediaUpdates['nextcloud_share_url'] = $firstMedia->nextcloud_share_url;
                    $mediaUpdates['nextcloud_file_id'] = $firstMedia->nextcloud_file_id;
                }
            }

            $post->forceFill(array_merge([
                'current_version_id' => $version->id,
                'status' => MarketingCampaignPostStatus::Generated,
                'generated_at' => now(),
                'title' => $newTitle,
                'description' => $newCaption,
            ], $mediaUpdates))->save();

            return CreateManualMarketingCampaignPostVersionResult::created($version);
        });
    }
}
