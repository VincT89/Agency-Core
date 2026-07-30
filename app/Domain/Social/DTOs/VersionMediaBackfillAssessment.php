<?php

namespace App\Domain\Social\DTOs;

use App\Domain\Social\Enums\VersionMediaBackfillClassification;

final readonly class VersionMediaBackfillAssessment
{
    /**
     * @param  list<int>  $mediaIds
     */
    public function __construct(
        public int $versionId,
        public int $postId,
        public VersionMediaBackfillClassification $classification,
        public array $mediaIds = [],
        public ?string $reason = null,
    ) {}

    public function isSafeToApply(): bool
    {
        return $this->classification->isSafeToApply() && $this->mediaIds !== [];
    }

    /**
     * @return array<int, array{sort_order: int}>
     */
    public function pivotPayload(): array
    {
        $payload = [];

        foreach ($this->mediaIds as $sortOrder => $mediaId) {
            $payload[$mediaId] = ['sort_order' => $sortOrder];
        }

        return $payload;
    }
}
