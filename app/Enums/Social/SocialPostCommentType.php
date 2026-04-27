<?php

namespace App\Enums\Social;

enum SocialPostCommentType: string
{
    case Comment = 'comment';
    case ChangeRequest = 'change_request';
}
