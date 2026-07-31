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
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::NotPrepared => 'Da preparare',
            self::Ready => 'Pronta, non inviata',
            self::Transmitting => 'Invio in corso',
            self::Sent => 'Inviata',
            self::Delivered => 'Consegnata',
            self::DeliveryFailed => 'Non consegnata',
            self::Rejected => 'Scartata',
        };
    }

    public function badgeStatus(): string
    {
        return match ($this) {
            self::NotPrepared => 'draft',
            self::Ready => 'pending',
            self::Transmitting => 'processing',
            self::Sent => 'issued',
            self::Delivered => 'paid',
            self::DeliveryFailed, self::Rejected => 'overdue',
        };
    }

    public function allowsEditing(): bool
    {
        return $this === self::NotPrepared;
    }

    public function hasLeftTheGestionale(): bool
    {
        return in_array($this, [
            self::Transmitting,
            self::Sent,
            self::Delivered,
            self::DeliveryFailed,
            self::Rejected,
        ], true);
    }
}
