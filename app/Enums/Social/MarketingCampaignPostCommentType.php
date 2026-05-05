<?php

namespace App\Enums\Social;

enum MarketingCampaignPostCommentType: string
{
    case Comment = 'comment';
    case ChangeRequest = 'change_request';
    case Approval = 'approval';

    public function label(): string
    {
        return match($this) {
            self::Comment => 'Commento',
            self::ChangeRequest => 'Richiesta di Modifiche',
            self::Approval => 'Approvazione',
        };
    }
}
