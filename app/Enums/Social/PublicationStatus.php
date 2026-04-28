<?php

namespace App\Enums\Social;

enum PublicationStatus: string
{
    case NotReady = 'not_ready';
    case Ready = 'ready';
    case Scheduled = 'scheduled';
    case Publishing = 'publishing';
    case Published = 'published';
    case Failed = 'failed';

    public function label(): string
    {
        return match($this) {
            self::NotReady => 'Non Pronta',
            self::Ready => 'Pronta',
            self::Scheduled => 'Programmata',
            self::Publishing => 'In Pubblicazione',
            self::Published => 'Pubblicato',
            self::Failed => 'Fallito',
        };
    }
}
