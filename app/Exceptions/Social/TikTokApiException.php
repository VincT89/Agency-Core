<?php

namespace App\Exceptions\Social;

use Exception;

class TikTokApiException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?string $requestId = null,
        public readonly ?int $httpStatus = null,
        public readonly ?array $responseData = null
    ) {
        parent::__construct($message);
    }
}
