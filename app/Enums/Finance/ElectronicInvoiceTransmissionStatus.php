<?php

namespace App\Enums\Finance;

enum ElectronicInvoiceTransmissionStatus: string
{
    case Processing = 'processing';
    case Validated = 'validated';
    case TakenCharge = 'taken_charge';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case DeliveryFailed = 'delivery_failed';
    case Undeliverable = 'undeliverable';
    case Rejected = 'rejected';
    case Accepted = 'accepted';
    case Refused = 'refused';
    case TermsExpired = 'terms_expired';
    case ProcessingError = 'processing_error';
    case Failed = 'failed';
    case Uncertain = 'uncertain';

    public function label(): string
    {
        return match ($this) {
            self::Processing => 'Operazione in corso',
            self::Validated => 'Controlli Aruba superati',
            self::TakenCharge => 'Presa in carico da Aruba',
            self::Sent => 'Inviata allo SdI',
            self::Delivered => 'Consegnata',
            self::DeliveryFailed => 'Non consegnata',
            self::Undeliverable => 'Recapito impossibile',
            self::Rejected => 'Scartata dallo SdI',
            self::Accepted => 'Accettata',
            self::Refused => 'Rifiutata dal destinatario',
            self::TermsExpired => 'Termini di risposta scaduti',
            self::ProcessingError => 'Errore di elaborazione',
            self::Failed => 'Operazione non completata',
            self::Uncertain => 'Esito da verificare',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Validated,
            self::Delivered,
            self::DeliveryFailed,
            self::Undeliverable,
            self::Rejected,
            self::Accepted,
            self::Refused,
            self::TermsExpired,
            self::ProcessingError,
            self::Failed,
        ], true);
    }

    public function badgeStatus(): string
    {
        return match ($this) {
            self::Validated,
            self::Delivered,
            self::Accepted => 'paid',
            self::Processing,
            self::Uncertain,
            self::TakenCharge => 'pending',
            self::Sent,
            self::TermsExpired => 'issued',
            self::DeliveryFailed,
            self::Undeliverable,
            self::Rejected,
            self::Refused,
            self::ProcessingError,
            self::Failed => 'overdue',
        };
    }
}
