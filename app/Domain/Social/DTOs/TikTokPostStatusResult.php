<?php

namespace App\Domain\Social\DTOs;

class TikTokPostStatusResult
{
    public function __construct(
        public readonly string $status,
        public readonly array $responseData,
        public readonly bool $isPermanentError = false,
        public readonly ?string $errorMessage = null,
        public readonly ?int $httpStatus = null,
        public readonly ?string $requestId = null,
        public readonly bool $isTemporaryError = false,
        public readonly bool $isAuthError = false,
        public readonly ?string $failReason = null,
    ) {}

    public function publicPostId(): ?string
    {
        $identifiers = $this->findPublicPostIdentifiers($this->responseData);

        return count($identifiers) === 1 ? $identifiers[0] : null;
    }

    private function findPublicPostIdentifiers(array $payload): array
    {
        $identifiers = [];

        foreach ($payload as $key => $value) {
            if (in_array($key, [
                'publicaly_available_post_id',
                'publicly_available_post_id',
            ], true)) {
                $values = is_array($value) ? $value : [$value];

                foreach ($values as $identifier) {
                    if (! is_string($identifier) && ! is_int($identifier)) {
                        continue;
                    }

                    $identifier = trim((string) $identifier);
                    if ($identifier !== '') {
                        $identifiers[] = $identifier;
                    }
                }

                continue;
            }

            if (is_array($value)) {
                $identifiers = array_merge(
                    $identifiers,
                    $this->findPublicPostIdentifiers($value)
                );
            }
        }

        return array_values(array_unique($identifiers));
    }
}
