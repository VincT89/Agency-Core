<?php

namespace App\Enums\Social;

enum SocialPostSource: string
{
    case N8n = 'n8n';
    case Manual = 'manual';
    case Regenerated = 'regenerated';
}
