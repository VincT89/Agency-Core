<?php

namespace App\Exceptions\Finance;

use RuntimeException;

class ArubaApiException extends RuntimeException
{
    public function __construct(
        public readonly string $userMessage,
        public readonly ?string $providerCode = null,
        public readonly bool $uncertain = false,
        public readonly ?array $responsePayload = null,
        public readonly ?int $httpStatus = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($userMessage, $httpStatus ?? 0, $previous);
    }
}
