<?php

namespace App\Domain\Social\TikTok;

use App\Enums\Social\PublicationStatus;

class TikTokPostStatusService
{
    /**
     * Mappa gli stati grezzi dell'API Content Posting di TikTok 
     * nel nostro enum interno PublicationStatus.
     */
    public function mapStatus(string $tiktokStatus): PublicationStatus
    {
        // Stati basati sulla documentazione TikTok Content Posting API
        // FAILED è solo per errori sistemici. Per problemi lato content, meglio NeedsManualReview
        return match ($tiktokStatus) {
            'PUBLISH_COMPLETE',
            'PUBLISHED' => PublicationStatus::Published,

            'PROCESSING_UPLOAD',
            'PROCESSING_DOWNLOAD' => PublicationStatus::Publishing,

            // Per draft/upload non è ancora "published":
            // TikTok ha consegnato la notifica inbox, ora serve azione dell'utente.
            'SEND_TO_USER_INBOX' => PublicationStatus::NeedsManualReview,

            'FAILED' => PublicationStatus::Failed,

            default => PublicationStatus::Publishing,
        };
    }
}
