<?php

namespace App\Enums\Social;

enum CommentSource: string
{
    case Internal = 'internal';
    case Client = 'client';
    case Operator = 'operator';
}
