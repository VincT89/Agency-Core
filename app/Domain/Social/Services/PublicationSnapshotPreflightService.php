<?php

namespace App\Domain\Social\Services;

use App\Enums\Social\SocialPlatform;
use App\Domain\Social\DTOs\PreflightResult;

class PublicationSnapshotPreflightService
{
    public function __construct(
        private MetaSnapshotPreflightRules $metaRules,
        private TikTokSnapshotPreflightRules $tiktokRules
    ) {}

    public function runPreflight(array $snapshotPayload): PreflightResult
    {
        $platform = SocialPlatform::tryFrom($snapshotPayload['platform'] ?? '');

        if (!$platform) {
            return new PreflightResult(false, ['Piattaforma non valida o non specificata.']);
        }

        return match ($platform) {
            SocialPlatform::Facebook, SocialPlatform::Instagram => $this->metaRules->validate($snapshotPayload),
            SocialPlatform::Tiktok => $this->tiktokRules->validate($snapshotPayload),
            default => new PreflightResult(false, ["Regole di preflight non definite per la piattaforma {$platform->value}"])
        };
    }
}
