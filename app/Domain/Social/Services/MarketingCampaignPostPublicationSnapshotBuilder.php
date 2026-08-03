<?php

namespace App\Domain\Social\Services;

use App\Domain\Social\DTOs\MarketingCampaignPostPublicationSnapshot;
use App\Enums\Social\SocialPlatform;
use App\Models\MarketingCampaignPostVersion;
use Carbon\Carbon;

class MarketingCampaignPostPublicationSnapshotBuilder
{
    public function build(
        MarketingCampaignPostVersion $version,
        SocialPlatform $platform,
        array $target,
        array $privacyOptions = [],
        string $publicationType = 'standard',
        array $mediaMetadataCache = [],
        array $platformOptions = []
    ): MarketingCampaignPostPublicationSnapshot {
        foreach (['social_account_id', 'external_id', 'page_id', 'profile_id'] as $key) {
            if (! array_key_exists($key, $target)) {
                throw new \InvalidArgumentException("Missing {$key} in target");
            }
        }

        if (! is_int($target['social_account_id']) || $target['social_account_id'] <= 0) {
            throw new \InvalidArgumentException('Invalid social_account_id in target');
        }

        if (! is_string($target['external_id']) || $target['external_id'] === '') {
            throw new \InvalidArgumentException('Invalid external_id in target');
        }

        foreach (['page_id', 'profile_id'] as $nullableId) {
            if ($target[$nullableId] !== null && (! is_string($target[$nullableId]) || $target[$nullableId] === '')) {
                throw new \InvalidArgumentException("Invalid {$nullableId} in target");
            }
        }

        $post = $version->post;

        // Risolvi media (ma non gli url temporanei)
        $mediaData = [];
        $mediaItems = $version->mediaItems()->get();

        foreach ($mediaItems as $media) {
            $cache = $mediaMetadataCache[$media->id] ?? [];

            $storageSource = in_array($media->source, ['nextcloud']) ? 'nextcloud' : 'local';

            if (! isset($media->mime_type)) {
                throw new \InvalidArgumentException("Missing mandatory mime_type for media {$media->id}");
            }
            if (! isset($media->media_type)) {
                throw new \InvalidArgumentException("Missing mandatory media_type for media {$media->id}");
            }
            if (! isset($media->pivot->sort_order)) {
                throw new \InvalidArgumentException("Missing mandatory sort_order for media {$media->id}");
            }

            if ($storageSource === 'local') {
                if (empty($media->disk) || empty($media->path)) {
                    throw new \InvalidArgumentException("Missing mandatory disk or path for local media {$media->id}");
                }
                if (! isset($cache['size_bytes']) || ! is_int($cache['size_bytes']) || $cache['size_bytes'] <= 0) {
                    throw new \InvalidArgumentException("Invalid mandatory size_bytes for local media {$media->id}");
                }
                if (empty($cache['sha256'])) {
                    throw new \InvalidArgumentException("Missing mandatory sha256 for local media {$media->id}");
                }

                $mediaData[] = [
                    'media_id' => $media->id,
                    'storage_source' => 'local',
                    'origin' => $media->source, // e.g. local, n8n, etc
                    'disk' => $media->disk,
                    'path' => $media->path,
                    'mime_type' => $media->mime_type,
                    'media_type' => $media->media_type,
                    'size_bytes' => (int) $cache['size_bytes'],
                    'sha256' => $cache['sha256'],
                    'sort_order' => (int) $media->pivot->sort_order,
                ];
            } else {
                // Nextcloud
                if (empty($media->nextcloud_path) || empty($media->nextcloud_file_id)) {
                    throw new \InvalidArgumentException("Missing mandatory nextcloud_path or nextcloud_file_id for nextcloud media {$media->id}");
                }
                if (empty($cache['etag'])) {
                    throw new \InvalidArgumentException("Missing mandatory nextcloud_etag for nextcloud media {$media->id}");
                }
                if (! isset($cache['size_bytes']) || ! is_int($cache['size_bytes']) || $cache['size_bytes'] <= 0) {
                    throw new \InvalidArgumentException("Invalid mandatory size_bytes for nextcloud media {$media->id}");
                }

                $mediaData[] = [
                    'media_id' => $media->id,
                    'storage_source' => 'nextcloud',
                    'origin' => $media->source,
                    'nextcloud_path' => $media->nextcloud_path,
                    'nextcloud_file_id' => $media->nextcloud_file_id,
                    'nextcloud_etag' => $cache['etag'],
                    'mime_type' => $media->mime_type,
                    'media_type' => $media->media_type,
                    'size_bytes' => (int) $cache['size_bytes'],
                    'sort_order' => (int) $media->pivot->sort_order,
                ];
            }
        }

        if (! isset($post->content_type)) {
            throw new \InvalidArgumentException("Missing mandatory content_type for post {$post->id}");
        }

        $scheduledTimeStr = null;
        if ($post->scheduled_time) {
            $scheduledTimeStr = $post->scheduled_time instanceof Carbon
                ? $post->scheduled_time->toTimeString()
                : (is_string($post->scheduled_time) ? $post->scheduled_time : null);
        }

        return new MarketingCampaignPostPublicationSnapshot(
            post_id: $post->id,
            version_id: $version->id,
            version_number: $version->version_number,
            provider: match ($platform) {
                SocialPlatform::Facebook, SocialPlatform::Instagram => 'meta',
                SocialPlatform::Tiktok => 'tiktok',
                default => $platform->value,
            },
            platform: $platform,
            social_account_id: $target['social_account_id'],
            account_external_id: $target['external_id'],
            page_id: $target['page_id'],
            profile_id: $target['profile_id'],
            privacy_options: $privacyOptions,
            publication_type: $publicationType,
            content_type: $post->content_type->value,
            title: $version->title ?? '',
            caption: $version->caption ?? '',
            hashtags: $version->hashtags ?? [],
            media: $mediaData,
            scheduled_date: $post->scheduled_date?->toDateString(),
            scheduled_time: $scheduledTimeStr,
            platform_options: $platformOptions,
            schema_version: 1
        );
    }
}
