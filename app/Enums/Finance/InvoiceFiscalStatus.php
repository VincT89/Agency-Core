<?php

namespace App\Enums\Finance;

enum InvoiceFiscalStatus: string
{
    case NotPrepared = 'not_prepared';
    case Ready = 'ready';
    case Transmitting = 'transmitting';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case DeliveryFailed = 'delivery_failed';
    case Undeliverable = 'undeliverable';
    case Rejected = 'rejected';
    case Accepted = 'accepted';
    case Refused = 'refused';
    case TermsExpired = 'terms_expired';
    case ProcessingError = 'processing_error';

    public function label(): string
    {
        return match ($this) {
            self::NotPrepared => 'Da preparare',
            self::Ready => 'Pronta, non inviata',
            self::Transmitting => 'Invio in corso',
            self::Sent => 'Inviata',
            self::Delivered => 'Consegnata',
            self::DeliveryFailed => 'Non consegnata',
            self::Undeliverable => 'Recapito impossibile',
            self::Rejected => 'Scartata',
            self::Accepted => 'Accettata',
            self::Refused => 'Rifiutata',
            self::TermsExpired => 'Termini decorsi',
            self::ProcessingError => 'Errore di elaborazione',
        };
    }

    public function badgeStatus(): string
    {
        return match ($this) {
            self::NotPrepared => 'draft',
            self::Ready => 'pending',
            self::Transmitting => 'processing',
            self::Sent => 'issued',
            self::Delivered, self::Accepted => 'paid',
            self::DeliveryFailed,
            self::Undeliverable,
            self::Rejected,
            self::Refused,
            self::ProcessingError => 'overdue',
            self::TermsExpired => 'pending',
        };
    }

    public function allowsEditing(): bool
    {
        return $this === self::NotPrepared;
    }

    public function allowsReopening(): bool
    {
        return in_array($this, [
            self::Ready,
            self::Rejected,
            self::ProcessingError,
        ], true);
    }

    public function hasLeftTheGestionale(): bool
    {
        return in_array($this, [
            self::Transmitting,
            self::Sent,
            self::Delivered,
            self::DeliveryFailed,
            self::Undeliverable,
            self::Rejected,
            self::Accepted,
            self::Refused,
            self::TermsExpired,
            self::ProcessingError,
        ], true);
    }
}
