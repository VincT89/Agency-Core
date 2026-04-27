<?php

namespace App\Domain\Media\Enums;

enum MediaVariantStatus: string
{
    case Original  = 'original';
    case Pending   = 'pending';
    case Processed = 'processed';
    case Failed    = 'failed';
    case Variant   = 'variant';
}
