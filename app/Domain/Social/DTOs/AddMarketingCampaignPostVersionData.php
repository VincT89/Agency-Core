<?php

namespace App\Domain\Social\DTOs;

use App\Enums\Social\MarketingCampaignPostRegenerationType;

final readonly class AddMarketingCampaignPostVersionData
{
    public function __construct(
        public int $postId,
        public ?string $requestId,
        public ?string $externalGenerationId,
        public MarketingCampaignPostRegenerationType $regenerationType,
        public ?string $title,
        public ?string $caption,
        public ?array $hashtags,
        public ?array $imageUrls,
        public ?string $promptUsed,
        public array $rawPayload,
    ) {}

    public static function fromArray(int $postId, array $data): self
    {
        $regenerationType = MarketingCampaignPostRegenerationType::tryFrom($data['regeneration_type'] ?? 'full') 
                            ?? MarketingCampaignPostRegenerationType::Full;

        $hashtags = $data['hashtags'] ?? null;
        if (is_string($hashtags)) {
            $hashtags = array_map('trim', explode(',', $hashtags));
        }

        $imageUrls = $data['image_urls'] ?? [];
        if (!empty($data['image_url']) && empty($imageUrls)) {
            $imageUrls = [$data['image_url']];
        }

        return new self(
            postId: $postId,
            requestId: self::normalizeString($data['request_id'] ?? null),
            externalGenerationId: self::normalizeString($data['external_generation_id'] ?? null),
            regenerationType: $regenerationType,
            title: self::normalizeString($data['title'] ?? null),
            caption: self::normalizeString($data['caption'] ?? null),
            hashtags: is_array($hashtags) ? array_values(array_filter($hashtags)) : null,
            imageUrls: is_array($imageUrls) ? array_values(array_filter($imageUrls)) : null,
            promptUsed: self::normalizeString($data['prompt_used'] ?? null),
            rawPayload: $data['raw_payload'] ?? $data,
        );
    }

    private static function normalizeString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
