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
            'PUBLISH_COMPLETE' => PublicationStatus::Published,
            'PROCESSING', 'UPLOADING', 'PENDING_REVIEW', 'MODERATION' => PublicationStatus::Publishing,
            'CREATOR_ACTION_REQUIRED', 'PRIVACY_RESTRICTION' => PublicationStatus::NeedsManualReview,
            'FAILED' => PublicationStatus::Failed,
            default => PublicationStatus::Publishing, // UNKNOWN o altri si considerano Publishing fino a timeout
        };
    }
}
