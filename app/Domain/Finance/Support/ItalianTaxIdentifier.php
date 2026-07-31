<?php

namespace App\Domain\Finance\Support;

final class ItalianTaxIdentifier
{
    public static function normalize(?string $value, ?string $countryCode = null): string
    {
        $normalized = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $value) ?? '');
        $countryCode = strtoupper((string) $countryCode);

        return $countryCode !== '' && str_starts_with($normalized, $countryCode)
            ? substr($normalized, 2)
            : $normalized;
    }

    public static function isValidVatNumber(?string $value): bool
    {
        $vatNumber = self::normalize($value, 'IT');

        if (! preg_match('/^\d{11}$/', $vatNumber)) {
            return false;
        }

        $sum = 0;

        for ($index = 0; $index < 10; $index++) {
            $digit = (int) $vatNumber[$index];

            if ($index % 2 === 0) {
                $sum += $digit;
            } else {
                $doubled = $digit * 2;
                $sum += $doubled > 9 ? $doubled - 9 : $doubled;
            }
        }

        $checkDigit = (10 - ($sum % 10)) % 10;

        return $checkDigit === (int) $vatNumber[10];
    }

    public static function isValidTaxCode(?string $value): bool
    {
        $taxCode = self::normalize($value);

        if (preg_match('/^\d{11}$/', $taxCode)) {
            return self::isValidVatNumber($taxCode);
        }

        if (! preg_match('/^[A-Z0-9]{16}$/', $taxCode)) {
            return false;
        }

        $oddValues = [
            '0' => 1, '1' => 0, '2' => 5, '3' => 7, '4' => 9,
            '5' => 13, '6' => 15, '7' => 17, '8' => 19, '9' => 21,
            'A' => 1, 'B' => 0, 'C' => 5, 'D' => 7, 'E' => 9,
            'F' => 13, 'G' => 15, 'H' => 17, 'I' => 19, 'J' => 21,
            'K' => 2, 'L' => 4, 'M' => 18, 'N' => 20, 'O' => 11,
            'P' => 3, 'Q' => 6, 'R' => 8, 'S' => 12, 'T' => 14,
            'U' => 16, 'V' => 10, 'W' => 22, 'X' => 25, 'Y' => 24, 'Z' => 23,
        ];
        $sum = 0;

        for ($index = 0; $index < 15; $index++) {
            $character = $taxCode[$index];
            $sum += $index % 2 === 0
                ? $oddValues[$character]
                : (ctype_digit($character) ? (int) $character : ord($character) - ord('A'));
        }

        return chr(ord('A') + ($sum % 26)) === $taxCode[15];
    }
}
