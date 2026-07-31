<?php

namespace App\Enums\Finance;

enum FatturaPaPaymentMethod: string
{
    case Cash = 'MP01';
    case Cheque = 'MP02';
    case BankDraft = 'MP03';
    case TreasuryCash = 'MP04';
    case BankTransfer = 'MP05';
    case BillOfExchange = 'MP06';
    case BankPaymentSlip = 'MP07';
    case PaymentCard = 'MP08';
    case DirectDebit = 'MP09';
    case UtilitiesDirectDebit = 'MP10';
    case FastDirectDebit = 'MP11';
    case Riba = 'MP12';
    case Mav = 'MP13';
    case TreasuryReceipt = 'MP14';
    case GiroTransfer = 'MP15';
    case BankDomiciliation = 'MP16';
    case PostalDomiciliation = 'MP17';
    case PostalSlip = 'MP18';
    case SepaDirectDebit = 'MP19';
    case SepaDirectDebitCore = 'MP20';
    case SepaDirectDebitB2B = 'MP21';
    case Withholding = 'MP22';
    case PagoPa = 'MP23';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Contanti',
            self::Cheque => 'Assegno',
            self::BankDraft => 'Assegno circolare',
            self::TreasuryCash => 'Contanti presso tesoreria',
            self::BankTransfer => 'Bonifico bancario',
            self::BillOfExchange => 'Vaglia cambiario',
            self::BankPaymentSlip => 'Bollettino bancario',
            self::PaymentCard => 'Carta di pagamento',
            self::DirectDebit => 'Addebito diretto',
            self::UtilitiesDirectDebit => 'Addebito diretto utenze',
            self::FastDirectDebit => 'Addebito diretto veloce',
            self::Riba => 'Ri.Ba.',
            self::Mav => 'MAV',
            self::TreasuryReceipt => 'Quietanza erario',
            self::GiroTransfer => 'Giroconto',
            self::BankDomiciliation => 'Domiciliazione bancaria',
            self::PostalDomiciliation => 'Domiciliazione postale',
            self::PostalSlip => 'Bollettino postale',
            self::SepaDirectDebit => 'Addebito SEPA',
            self::SepaDirectDebitCore => 'Addebito SEPA CORE',
            self::SepaDirectDebitB2B => 'Addebito SEPA B2B',
            self::Withholding => 'Trattenuta su somme riscosse',
            self::PagoPa => 'PagoPA',
        };
    }

    public function requiresIban(): bool
    {
        return in_array($this, [
            self::BankTransfer,
            self::DirectDebit,
            self::UtilitiesDirectDebit,
            self::FastDirectDebit,
            self::Riba,
            self::BankDomiciliation,
            self::SepaDirectDebit,
            self::SepaDirectDebitCore,
            self::SepaDirectDebitB2B,
        ], true);
    }
}
