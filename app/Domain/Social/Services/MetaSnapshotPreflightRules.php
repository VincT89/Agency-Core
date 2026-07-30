<?php

namespace App\Domain\Social\Services;

use App\Domain\Social\DTOs\PreflightResult;
use App\Enums\Social\SocialPlatform;

class MetaSnapshotPreflightRules
{
    public function validate(array $snapshotPayload): PreflightResult
    {
        $errors = [];
        $media = $snapshotPayload['media'] ?? [];
        $platform = SocialPlatform::tryFrom($snapshotPayload['platform'] ?? '');

        if (! is_array($media) || ! array_is_list($media)) {
            return new PreflightResult(
                false,
                ['Meta media must be a valid list.']
            );
        }

        if (empty($media) && empty($snapshotPayload['caption'])) {
            $errors[] = 'Meta requires either a caption or at least one media file.';
        }

        foreach ($media as $item) {
            if (! is_array($item)) {
                $errors[] = 'Meta media descriptor is invalid.';

                break;
            }

            $type = strtolower((string) ($item['media_type'] ?? ''));
            $mime = strtolower((string) ($item['mime_type'] ?? ''));
            $validImage = in_array($type, ['image', 'photo'], true)
                && in_array($mime, [
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                ], true);
            $validVideo = $type === 'video'
                && in_array($mime, [
                    'video/mp4',
                    'video/quicktime',
                    'video/webm',
                ], true);

            if (! $validImage && ! $validVideo) {
                $errors[] = 'Meta media format is not supported.';

                break;
            }
        }

        if ($platform === SocialPlatform::Facebook) {
            if (count($media) > 10) {
                $errors[] = 'Facebook supports a maximum of 10 images in one carousel.';
            }
            if (count($media) > 1 && collect($media)->contains(
                fn (mixed $item): bool => ! is_array($item)
                    || ! in_array(
                        strtolower((string) ($item['media_type'] ?? '')),
                        ['image', 'photo'],
                        true
                    )
            )) {
                $errors[] = 'Facebook carousels support images only.';
            }
        } elseif ($platform === SocialPlatform::Instagram) {
            if (empty($media)) {
                $errors[] = 'Instagram requires at least one image or video.';
            }
            if (count($media) > 10) {
                $errors[] = 'Instagram supports a maximum of 10 media items per carousel.';
            }
            if (
                ($snapshotPayload['target']['publication_type'] ?? null) === 'reel'
                && (
                    count($media) !== 1
                    || ! is_array($media[0])
                    || strtolower((string) ($media[0]['media_type'] ?? '')) !== 'video'
                )
            ) {
                $errors[] = 'Instagram Reels require exactly one video.';
            }
        }

        return new PreflightResult(
            isPass: empty($errors),
            errors: $errors
        );
    }
}
