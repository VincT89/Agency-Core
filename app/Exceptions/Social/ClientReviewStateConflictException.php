<?php

namespace App\Exceptions\Social;

use Exception;

class ClientReviewStateConflictException extends Exception
{
    public function __construct(string $message = "Lo stato attuale del post non permette una nuova revisione.", int $code = 409, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
