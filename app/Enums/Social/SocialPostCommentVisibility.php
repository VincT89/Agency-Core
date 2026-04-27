<?php

namespace App\Enums\Social;

enum SocialPostCommentVisibility: string
{
    case Internal = 'internal';
    case Client = 'client';
}
