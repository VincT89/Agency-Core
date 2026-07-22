<?php

namespace App\Domain\Social\DTOs;

class CreateManualMarketingCampaignPostVersionData
{
    public function __construct(
        public readonly ?int $expected_current_version_id,
        public readonly ?string $title,
        public readonly ?string $caption,
        public readonly ?array $hashtags,
        public readonly array $ordered_media_ids,
        public readonly ?int $author_id = null,
        public readonly ?string $notes = null
    ) {
    }
}
