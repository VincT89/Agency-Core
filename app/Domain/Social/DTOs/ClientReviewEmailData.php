<?php

namespace App\Domain\Social\DTOs;

final readonly class ClientReviewEmailData
{
    public function __construct(
        public string $clientName,
        public string $campaignName,
        public int $postId,
        public int $versionId,
        public int $versionNumber,
        public string $postTitle,
        public string $postCaption,
        public array $previewUrls,
        public string $reviewUrl,
        public ?string $expiresAt,
    ) {}
}
