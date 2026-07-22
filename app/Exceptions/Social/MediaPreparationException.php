<?php

namespace App\Exceptions\Social;

use Exception;

class MediaPreparationException extends Exception
{
    public function __construct($message = "Errore durante la preparazione dei media.")
    {
        parent::__construct($message);
    }
}
