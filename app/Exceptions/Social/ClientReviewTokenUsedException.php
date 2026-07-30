<?php

namespace App\Exceptions\Social;

use Exception;

class ClientReviewTokenUsedException extends Exception
{
    public function __construct(string $message = "Questo link di revisione è già stato utilizzato.", int $code = 409, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
