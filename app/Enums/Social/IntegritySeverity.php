<?php

namespace App\Enums\Social;

enum IntegritySeverity: string
{
    case Error = 'error';
    case Temporary = 'temporary';
}
