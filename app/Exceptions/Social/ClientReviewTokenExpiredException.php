<?php

namespace App\Exceptions\Social;

use Exception;

class ClientReviewTokenExpiredException extends Exception
{
    public function __construct(string $message = "Questo link di revisione è scaduto.", int $code = 403, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
