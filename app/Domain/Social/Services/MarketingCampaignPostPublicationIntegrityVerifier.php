<?php

namespace App\Domain\Social\Services;

use App\Domain\Social\DTOs\MarketingCampaignPostPublicationSnapshot;
use App\Domain\Social\DTOs\PublicationIntegrityResult;
use App\Enums\Social\IntegritySeverity;
use App\Enums\Social\SocialPlatform;
use App\Exceptions\Social\NextcloudFileNotFoundException;
use App\Exceptions\Social\NextcloudPermanentFailureException;
use App\Exceptions\Social\NextcloudTemporaryUnavailableException;
use App\Models\MarketingCampaignPostPublication;
use App\Services\Integrations\Nextcloud\NextcloudService;
use Illuminate\Support\Facades\Storage;

class MarketingCampaignPostPublicationIntegrityVerifier
{
    public function __construct(
        private CanonicalJsonEncoder $encoder,
        private NextcloudService $nextcloudService
    ) {}

    public function verify(MarketingCampaignPostPublication $publication): PublicationIntegrityResult
    {
        $payload = $publication->payload_snapshot;

        if (! is_array($payload) || $payload === []) {
            return $this->error('Payload is empty or is not a valid array');
        }

        if (! is_string($publication->snapshot_hash) ||
            preg_match('/^[a-f0-9]{64}$/', $publication->snapshot_hash) !== 1) {
            return $this->error('Snapshot hash is missing or malformed');
        }

        if ($publication->snapshot_schema_version !== 1) {
            return $this->error(
                "Unsupported schema version: {$publication->snapshot_schema_version}"
            );
        }

        $requiredKeys = [
            'post_id',
            'version_id',
            'version_number',
            'provider',
            'platform',
            'target',
            'content_type',
            'title',
            'caption',
            'hashtags',
            'media',
            'scheduled_date',
            'scheduled_time',
            'platform_options',
            'schema_version',
        ];

        if ($missing = $this->firstMissingKey($payload, $requiredKeys)) {
            return $this->error("Missing required field in snapshot: {$missing}");
        }

        if (
            ! $this->isPositiveInt($payload['post_id']) ||
            ! $this->isPositiveInt($payload['version_id']) ||
            ! $this->isPositiveInt($payload['version_number'])
        ) {
            return $this->error('post_id, version_id and version_number must be positive integers');
        }

        foreach (['provider', 'platform', 'content_type', 'title', 'caption'] as $field) {
            if (! is_string($payload[$field])) {
                return $this->error("Snapshot field {$field} must be a string");
            }
        }

        foreach (['provider', 'platform', 'content_type', 'title'] as $field) {
            if ($payload[$field] === '') {
                return $this->error("Snapshot field {$field} cannot be empty");
            }
        }

        if (! is_int($payload['schema_version']) || $payload['schema_version'] !== 1) {
            return $this->error('Payload schema_version must be the integer 1');
        }

        if ($payload['schema_version'] !== $publication->snapshot_schema_version) {
            return $this->error('Schema version mismatch between DB column and payload');
        }

        if (! $this->isNullableString($payload['scheduled_date']) ||
            ! $this->isNullableString($payload['scheduled_time'])) {
            return $this->error('scheduled_date and scheduled_time must be strings or null');
        }

        if (! is_array($payload['hashtags']) || ! array_is_list($payload['hashtags'])) {
            return $this->error('hashtags must be a list');
        }

        foreach ($payload['hashtags'] as $hashtag) {
            if (! is_string($hashtag)) {
                return $this->error('Every hashtag must be a string');
            }
        }

        if (! is_array($payload['platform_options'])) {
            return $this->error('platform_options must be an array');
        }

        if (! is_array($payload['target'])) {
            return $this->error('Target field in snapshot is not a valid array');
        }

        $targetKeys = [
            'social_account_id',
            'external_id',
            'page_id',
            'profile_id',
            'privacy_options',
            'publication_type',
        ];

        if ($missing = $this->firstMissingKey($payload['target'], $targetKeys)) {
            return $this->error("Missing required field in snapshot target: {$missing}");
        }

        $target = $payload['target'];

        if (! $this->isPositiveInt($target['social_account_id'])) {
            return $this->error('target.social_account_id must be a positive integer');
        }

        if (! is_string($target['external_id']) || $target['external_id'] === '') {
            return $this->error('target.external_id must be a non-empty string');
        }

        foreach (['page_id', 'profile_id'] as $field) {
            if (! $this->isNullableNonEmptyString($target[$field])) {
                return $this->error("target.{$field} must be a non-empty string or null");
            }
        }

        if (! is_array($target['privacy_options'])) {
            return $this->error('target.privacy_options must be an array');
        }

        if (! is_string($target['publication_type']) || $target['publication_type'] === '') {
            return $this->error('target.publication_type must be a non-empty string');
        }

        $platform = SocialPlatform::tryFrom($payload['platform']);
        if (! $platform) {
            return $this->error("Invalid platform in snapshot: {$payload['platform']}");
        }

        if ($platform !== $publication->platform) {
            return $this->error(
                "Platform mismatch: snapshot has {$platform->value} ".
                "but publication has {$publication->platform->value}"
            );
        }

        $expectedProvider = match ($platform) {
            SocialPlatform::Facebook, SocialPlatform::Instagram => 'meta',
            SocialPlatform::Tiktok => 'tiktok',
        };

        if ($payload['provider'] !== $expectedProvider) {
            return $this->error(
                "Provider mismatch: expected {$expectedProvider} for platform {$platform->value}"
            );
        }

        if (! is_array($payload['media']) || ! array_is_list($payload['media'])) {
            return $this->error('media must be a list');
        }

        if ($mediaError = $this->validateMediaSchema($payload['media'])) {
            return $mediaError;
        }

        try {
            $snapshot = new MarketingCampaignPostPublicationSnapshot(
                post_id: $payload['post_id'],
                version_id: $payload['version_id'],
                version_number: $payload['version_number'],
                provider: $payload['provider'],
                platform: $platform,
                social_account_id: $target['social_account_id'],
                account_external_id: $target['external_id'],
                page_id: $target['page_id'],
                profile_id: $target['profile_id'],
                privacy_options: $target['privacy_options'],
                publication_type: $target['publication_type'],
                content_type: $payload['content_type'],
                title: $payload['title'],
                caption: $payload['caption'],
                hashtags: $payload['hashtags'],
                media: $payload['media'],
                scheduled_date: $payload['scheduled_date'],
                scheduled_time: $payload['scheduled_time'],
                platform_options: $payload['platform_options'],
                schema_version: $payload['schema_version']
            );

            $calculatedHash = hash('sha256', $this->encoder->encode($snapshot));
        } catch (\Throwable $e) {
            return $this->error('Failed to rebuild canonical snapshot: '.$e->getMessage());
        }

        if (! hash_equals($publication->snapshot_hash, $calculatedHash)) {
            return $this->error('Snapshot hash mismatch');
        }

        if (
            $payload['post_id'] !== $publication->marketing_campaign_post_id ||
            $payload['version_id'] !== $publication->marketing_campaign_post_version_id ||
            $target['social_account_id'] !== $publication->client_social_account_id
        ) {
            return $this->error('Snapshot identity does not match the publication database record');
        }

        foreach ($payload['media'] as $mediaItem) {
            $storageResult = $mediaItem['storage_source'] === 'local'
                ? $this->verifyLocalMedia($mediaItem)
                : $this->verifyNextcloudMedia($mediaItem);

            if ($storageResult !== null) {
                return $storageResult;
            }
        }

        return new PublicationIntegrityResult(true);
    }

    private function validateMediaSchema(array $media): ?PublicationIntegrityResult
    {
        $mediaIds = [];
        $sortOrders = [];

        foreach ($media as $index => $item) {
            if (! is_array($item)) {
                return $this->error("Media item at index {$index} must be an array");
            }

            $required = [
                'media_id',
                'storage_source',
                'mime_type',
                'media_type',
                'size_bytes',
                'sort_order',
            ];

            if ($missing = $this->firstMissingKey($item, $required)) {
                return $this->error("Media item at index {$index} is missing {$missing}");
            }

            if (! $this->isPositiveInt($item['media_id'])) {
                return $this->error("Media item at index {$index} has an invalid media_id");
            }

            if (! is_int($item['sort_order']) || $item['sort_order'] < 0) {
                return $this->error("Media {$item['media_id']} has an invalid sort_order");
            }

            if (in_array($item['media_id'], $mediaIds, true)) {
                return $this->error("Duplicate media_id in snapshot: {$item['media_id']}");
            }

            if (in_array($item['sort_order'], $sortOrders, true)) {
                return $this->error("Duplicate sort_order in snapshot: {$item['sort_order']}");
            }

            $mediaIds[] = $item['media_id'];
            $sortOrders[] = $item['sort_order'];

            if (! in_array($item['storage_source'], ['local', 'nextcloud'], true)) {
                return $this->error("Unknown storage_source: {$item['storage_source']}");
            }

            if (! is_string($item['mime_type']) || $item['mime_type'] === '' ||
                ! is_string($item['media_type']) || $item['media_type'] === '') {
                return $this->error("Media {$item['media_id']} has invalid mime_type or media_type");
            }

            if (! $this->isPositiveInt($item['size_bytes'])) {
                return $this->error(
                    "Media {$item['media_id']} size_bytes must be a positive integer"
                );
            }

            if ($item['storage_source'] === 'local') {
                foreach (['disk', 'path', 'sha256'] as $field) {
                    if (! isset($item[$field]) || ! is_string($item[$field]) || $item[$field] === '') {
                        return $this->error(
                            "Local media {$item['media_id']} has invalid or missing {$field}"
                        );
                    }
                }

                if (preg_match('/^[a-f0-9]{64}$/', $item['sha256']) !== 1) {
                    return $this->error("Local media {$item['media_id']} has a malformed sha256");
                }
            } else {
                foreach (['nextcloud_path', 'nextcloud_file_id', 'nextcloud_etag'] as $field) {
                    if (! isset($item[$field]) || ! is_string($item[$field]) || $item[$field] === '') {
                        return $this->error(
                            "Nextcloud media {$item['media_id']} has invalid or missing {$field}"
                        );
                    }
                }
            }
        }

        return null;
    }

    private function verifyLocalMedia(array $media): ?PublicationIntegrityResult
    {
        $disk = $media['disk'];
        $path = $media['path'];

        try {
            if (! Storage::disk($disk)->exists($path)) {
                return $this->error("Media file missing on disk: {$disk}::{$path}");
            }

            $actualSize = Storage::disk($disk)->size($path);
            if ($actualSize !== $media['size_bytes']) {
                return $this->error(
                    "Media file size mismatch for {$path}. Expected {$media['size_bytes']}, got {$actualSize}"
                );
            }

            $actualMime = Storage::disk($disk)->mimeType($path);
            if ($actualMime !== $media['mime_type']) {
                return $this->error(
                    "Media file mime_type mismatch for {$path}. ".
                    "Expected {$media['mime_type']}, got {$actualMime}"
                );
            }

            $actualHash = hash_file('sha256', Storage::disk($disk)->path($path));
            if (! is_string($actualHash) || ! hash_equals($media['sha256'], $actualHash)) {
                return $this->error("Media file checksum mismatch for {$path}");
            }
        } catch (\Throwable $e) {
            return $this->temporary(
                'Media file verification failed: '.$e->getMessage()
            );
        }

        return null;
    }

    private function verifyNextcloudMedia(array $media): ?PublicationIntegrityResult
    {
        try {
            $fileInfo = $this->nextcloudService->getFileInfo($media['nextcloud_path']);

            if ($media['nextcloud_etag'] !== $fileInfo->etag) {
                return $this->error(
                    "Nextcloud media file ETag mismatch for {$media['nextcloud_path']}"
                );
            }

            if ($media['nextcloud_file_id'] !== $fileInfo->fileId) {
                return $this->error(
                    "Nextcloud media file ID mismatch for {$media['nextcloud_path']}"
                );
            }

            if ($media['size_bytes'] !== $fileInfo->sizeBytes) {
                return $this->error(
                    "Nextcloud media size mismatch for {$media['nextcloud_path']}"
                );
            }

            if ($media['mime_type'] !== $fileInfo->mimeType) {
                return $this->error(
                    "Nextcloud media mime type mismatch for {$media['nextcloud_path']}"
                );
            }
        } catch (NextcloudFileNotFoundException|NextcloudPermanentFailureException $e) {
            return $this->error('Nextcloud verification failed: '.$e->getMessage());
        } catch (NextcloudTemporaryUnavailableException $e) {
            return $this->temporary('Nextcloud temporarily unavailable: '.$e->getMessage());
        } catch (\Throwable $e) {
            return $this->temporary('Nextcloud verification failed: '.$e->getMessage());
        }

        return null;
    }

    private function firstMissingKey(array $data, array $required): ?string
    {
        foreach ($required as $key) {
            if (! array_key_exists($key, $data)) {
                return $key;
            }
        }

        return null;
    }

    private function isPositiveInt(mixed $value): bool
    {
        return is_int($value) && $value > 0;
    }

    private function isNullableString(mixed $value): bool
    {
        return $value === null || is_string($value);
    }

    private function isNullableNonEmptyString(mixed $value): bool
    {
        return $value === null || (is_string($value) && $value !== '');
    }

    private function error(string $message): PublicationIntegrityResult
    {
        return new PublicationIntegrityResult(
            false,
            [$message],
            IntegritySeverity::Error,
            false
        );
    }

    private function temporary(string $message): PublicationIntegrityResult
    {
        return new PublicationIntegrityResult(
            false,
            [$message],
            IntegritySeverity::Temporary,
            true
        );
    }
}
