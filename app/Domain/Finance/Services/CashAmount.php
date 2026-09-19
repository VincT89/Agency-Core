<?php

namespace App\Domain\Finance\Services;

use InvalidArgumentException;

class CashAmount
{
    public static function cents(string|int|float $amount): int
    {
        $value = is_float($amount) ? number_format($amount, 2, '.', '') : (string) $amount;
        if (! preg_match('/^([+-]?)(\d*)(?:\.(\d{0,2}))?$/', $value, $parts)
            || (($parts[2] ?? '') === '' && ($parts[3] ?? '') === '')) {
            throw new InvalidArgumentException('Importo non valido.');
        }
        $cents = (int) $parts[2] * 100 + (int) str_pad($parts[3] ?? '', 2, '0');

        return $parts[1] === '-' ? -$cents : $cents;
    }

    public static function decimal(int $cents): string
    {
        return ($cents < 0 ? '-' : '').intdiv(abs($cents), 100).'.'.str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }
}
