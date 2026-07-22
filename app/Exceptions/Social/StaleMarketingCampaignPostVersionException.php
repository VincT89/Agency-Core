<?php

namespace App\Exceptions\Social;

use Exception;

class StaleMarketingCampaignPostVersionException extends Exception
{
    public function __construct(string $message = "Il post è stato modificato in un'altra sessione. Ricarica la pagina.")
    {
        parent::__construct($message);
    }
}
