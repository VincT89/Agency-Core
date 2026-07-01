<?php

namespace App\Enums\Social;

enum PublicationStatus: string
{
    case Pending = 'pending';
    case Publishing = 'publishing';
    case Published = 'published';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case NeedsManualReview = 'needs_manual_review';
    case Superseded = 'superseded';
    case Abandoned = 'abandoned';

    public function label(): string
    {
        return match($this) {
            self::Pending => 'In Attesa',
            self::Publishing => 'In Pubblicazione',
            self::Published => 'Pubblicato',
            self::Failed => 'Fallito',
            self::Cancelled => 'Annullato',
            self::NeedsManualReview => 'Richiede Revisione Manuale',
            self::Superseded => 'Sostituito',
            self::Abandoned => 'Abbandonato',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::Pending => 'bd',
            self::Publishing => 'bb',
            self::Published => 'bg',
            self::Failed => 'br',
            self::Cancelled => 'bd',
            self::NeedsManualReview => 'bo', // bo for orange maybe?
            self::Superseded => 'bd',
            self::Abandoned => 'bd',
        };
    }
}
