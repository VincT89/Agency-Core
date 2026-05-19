<?php

namespace App\Exceptions\Social;

use Exception;

class ContainerProcessingException extends Exception
{
    // Usata per triggerare il backoff di Laravel in modo naturale 
    // quando il container di Instagram è in stato IN_PROGRESS.
}
