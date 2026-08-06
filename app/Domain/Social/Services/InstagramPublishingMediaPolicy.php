<?php

namespace App\Domain\Social\Services;

final class InstagramPublishingMediaPolicy
{
    public const VIDEO_MAX_MEGABYTES = 1024;

    public const VIDEO_MAX_BYTES = self::VIDEO_MAX_MEGABYTES * 1024 * 1024;

    public const VIDEO_MIME_TYPES = [
        'video/mp4',
        'video/quicktime',
    ];

    public const VIDEO_EXTENSIONS = [
        'mp4',
        'mov',
    ];

    public static function supportsVideo(?string $mimeType, ?string $path): bool
    {
        $normalizedMimeType = strtolower(trim((string) $mimeType));

        if ($normalizedMimeType !== '') {
            return in_array($normalizedMimeType, self::VIDEO_MIME_TYPES, true);
        }

        $extension = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));

        return in_array($extension, self::VIDEO_EXTENSIONS, true);
    }

    public static function exceedsVideoSizeLimit(mixed $sizeBytes): bool
    {
        return is_numeric($sizeBytes)
            && (int) $sizeBytes > self::VIDEO_MAX_BYTES;
    }
}
