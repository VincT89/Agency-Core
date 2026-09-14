<?php

namespace App\Domain\Quotes;

class QuoteAmounts
{
    public static function hundredths(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    public static function decimal(int $hundredths): string
    {
        return intdiv($hundredths, 100).'.'.str_pad((string) ($hundredths % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function lineTotal(string $quantity, string $unitPrice): string
    {
        return self::decimal(intdiv(self::hundredths($quantity) * self::hundredths($unitPrice) + 50, 100));
    }
}
