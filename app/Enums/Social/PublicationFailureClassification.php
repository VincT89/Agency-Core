<?php

namespace App\Enums\Social;

enum PublicationFailureClassification: string
{
    case Temporary = 'temporary';
    case Permanent = 'permanent';
    case ManualReview = 'manual_review';
}
