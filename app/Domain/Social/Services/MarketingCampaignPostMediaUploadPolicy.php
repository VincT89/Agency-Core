<?php

namespace App\Domain\Social\Services;

use Closure;
use Illuminate\Http\UploadedFile;

final class MarketingCampaignPostMediaUploadPolicy
{
    public const IMAGE_MAX_MEGABYTES = 200;

    public const VIDEO_MAX_MEGABYTES = 500;

    public const IMAGE_MAX_KILOBYTES = self::IMAGE_MAX_MEGABYTES * 1024;

    public const VIDEO_MAX_KILOBYTES = self::VIDEO_MAX_MEGABYTES * 1024;

    public const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'video/mp4',
        'video/webm',
        'video/quicktime',
    ];

    /**
     * @return array<int, mixed>
     */
    public static function validationRules(): array
    {
        return [
            'bail',
            'file',
            'mimetypes:'.implode(',', self::ALLOWED_MIME_TYPES),
            function (string $attribute, mixed $value, Closure $fail): void {
                if (! $value instanceof UploadedFile) {
                    return;
                }

                $isVideo = str_starts_with($value->getMimeType(), 'video/');
                $maxKilobytes = $isVideo
                    ? self::VIDEO_MAX_KILOBYTES
                    : self::IMAGE_MAX_KILOBYTES;

                if ($value->getSize() <= $maxKilobytes * 1024) {
                    return;
                }

                $fail($isVideo
                    ? 'Il video non può superare '.self::VIDEO_MAX_MEGABYTES.' MB.'
                    : 'La foto non può superare '.self::IMAGE_MAX_MEGABYTES.' MB.');
            },
        ];
    }
}
