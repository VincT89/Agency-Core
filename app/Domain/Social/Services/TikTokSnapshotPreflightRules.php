<?php

namespace App\Domain\Social\Services;

use App\Domain\Social\DTOs\PreflightResult;

class TikTokSnapshotPreflightRules
{
    public function validate(array $snapshotPayload): PreflightResult
    {
        $errors = [];
        $media = $snapshotPayload['media'] ?? [];

        if (! is_array($media) || $media === []) {
            return new PreflightResult(false, [
                'TikTok requires one video or at least one photo.',
            ]);
        }

        $videoItems = collect($media)->filter(
            fn (array $item): bool => strtolower($item['media_type'] ?? '') === 'video'
                || str_starts_with(strtolower($item['mime_type'] ?? ''), 'video/')
        );
        $photoItems = collect($media)->reject(
            fn (array $item): bool => strtolower($item['media_type'] ?? '') === 'video'
                || str_starts_with(strtolower($item['mime_type'] ?? ''), 'video/')
        );

        if ($videoItems->isNotEmpty() && $photoItems->isNotEmpty()) {
            $errors[] = 'TikTok does not support mixed photo and video posts.';
        } elseif ($videoItems->isNotEmpty()) {
            if ($videoItems->count() !== 1 || count($media) !== 1) {
                $errors[] = 'TikTok video posts require exactly one video.';
            }

            $mime = strtolower($videoItems->first()['mime_type'] ?? '');
            if (! in_array($mime, [
                'video/mp4',
                'video/quicktime',
                'video/webm',
            ], true)) {
                $errors[] = 'TikTok video format is not supported.';
            }
        } else {
            $maxPhotos = max(
                1,
                (int) config('services.tiktok.max_photo_count', 10)
            );
            if ($photoItems->count() > $maxPhotos) {
                $errors[] = "TikTok supports at most {$maxPhotos} photos.";
            }

            foreach ($photoItems as $item) {
                if (! in_array(strtolower($item['mime_type'] ?? ''), [
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                ], true)) {
                    $errors[] = 'TikTok photo format is not supported.';
                    break;
                }
            }
        }

        if (config('services.tiktok.delivery_mode') === 'direct') {
            $privacyLevel = $snapshotPayload['target']['privacy_options']['privacy_level']
                ?? $snapshotPayload['platform_options']['privacy_level']
                ?? null;
            if (! in_array($privacyLevel, [
                'PUBLIC_TO_EVERYONE',
                'MUTUAL_FOLLOW_FRIENDS',
                'FOLLOWER_OF_CREATOR',
                'SELF_ONLY',
            ], true)) {
                $errors[] = 'TikTok Direct Post requires an explicit valid privacy level.';
            }

            $privacyOptions = $snapshotPayload['target']['privacy_options'] ?? [];
            foreach (['disable_comment', 'disable_duet', 'disable_stitch'] as $interactionOption) {
                if (! array_key_exists($interactionOption, $privacyOptions)
                    || ! is_bool($privacyOptions[$interactionOption])) {
                    $errors[] = "TikTok Direct Post requires {$interactionOption} to be explicitly selected.";
                }
            }

            $platformOptions = $snapshotPayload['platform_options'] ?? [];
            if (($platformOptions['delivery_mode'] ?? null) !== 'direct') {
                $errors[] = 'TikTok Direct Post snapshot delivery mode is missing.';
            }
            if (($platformOptions['creator_consent_confirmed'] ?? false) !== true) {
                $errors[] = 'TikTok Direct Post requires explicit creator consent.';
            }

            $commercialContent = (bool) ($platformOptions['commercial_content_disclosed'] ?? false);
            $brandContent = (bool) ($platformOptions['brand_content_toggle'] ?? false);
            $brandOrganic = (bool) ($platformOptions['brand_organic_toggle'] ?? false);

            if ($commercialContent && ! $brandContent && ! $brandOrganic) {
                $errors[] = 'TikTok commercial content requires at least one disclosure selection.';
            }
            if ($brandContent && ! in_array($privacyLevel, [
                'PUBLIC_TO_EVERYONE',
                'MUTUAL_FOLLOW_FRIENDS',
            ], true)) {
                $errors[] = 'TikTok branded content cannot use the selected privacy level.';
            }
        }

        return new PreflightResult(
            isPass: $errors === [],
            errors: $errors
        );
    }
}
