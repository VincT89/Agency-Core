<?php

namespace App\Enums\Shooting;

enum ShootStatus: string
{
    case Draft = 'draft';
    case WaitingPhotographer = 'waiting_photographer';
    case PhotographerRejected = 'photographer_rejected';
    case WaitingClient = 'waiting_client';
    case ClientRejected = 'client_rejected';
    case ClientConfirmed = 'client_confirmed';
    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';
    
    public function label(): string
    {
        return match($this) {
            self::Draft => 'Bozza',
            self::WaitingPhotographer => 'In attesa del Fotografo',
            self::PhotographerRejected => 'Rifiutato dal Fotografo',
            self::WaitingClient => 'In attesa del Cliente',
            self::ClientRejected => 'Rifiutato dal Cliente',
            self::ClientConfirmed => 'Confermato dal Cliente',
            self::Scheduled => 'Pianificato',
            self::Cancelled => 'Annullato',
        };
    }

    public function labelForContext(string $context): string
    {
        if ($context === 'photography') {
            return match($this) {
                self::WaitingPhotographer => 'Da rispondere',
                self::WaitingClient => 'In attesa cliente',
                self::Scheduled => 'Pianificato',
                self::ClientRejected => 'Annullato (Cliente ha rifiutato)',
                default => $this->label(),
            };
        }

        if ($context === 'social') {
            return match($this) {
                self::WaitingPhotographer => 'In attesa risposta fotografo',
                self::WaitingClient => 'In attesa conferma cliente',
                default => $this->label(),
            };
        }

        // admin / default
        return $this->label();
    }
}
