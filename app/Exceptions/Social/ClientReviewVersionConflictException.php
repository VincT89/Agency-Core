<?php

namespace App\Exceptions\Social;

use Exception;

class ClientReviewVersionConflictException extends Exception
{
    public function __construct(string $message = "Questo link appartiene a una versione obsoleta del post. Ricarica la pagina o contatta il team per un nuovo link.", int $code = 409, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
