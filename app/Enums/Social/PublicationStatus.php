<?php

namespace App\Enums\Social;

enum PublicationStatus: string
{
    case Pending = 'pending';
    case NotReady = 'not_ready';
    case Ready = 'ready';
    case Scheduled = 'scheduled';
    case Publishing = 'publishing';
    case Published = 'published';
    case Failed = 'failed';
    case NeedsManualReview = 'needs_manual_review';
    case Abandoned = 'abandoned';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match($this) {
            self::Pending => 'In Attesa',
            self::NotReady => 'Non Pronta',
            self::Ready => 'Pronta',
            self::Scheduled => 'Programmata',
            self::Publishing => 'In Pubblicazione',
            self::Published => 'Pubblicato',
            self::Failed => 'Fallito',
            self::NeedsManualReview => 'Richiede Revisione Manuale',
            self::Abandoned => 'Abbandonato',
            self::Superseded => 'Sostituito (Retry)',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::Pending => 'bd',
            self::NotReady => 'bd',
            self::Ready => 'bb',
            self::Scheduled => 'bp',
            self::Publishing => 'bb',
            self::Published => 'bg',
            self::Failed => 'br',
            self::NeedsManualReview => 'ba',
            self::Abandoned => 'bd',
            self::Superseded => 'bd',
        };
    }
}
