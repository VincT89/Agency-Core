<?php

namespace App\Services\Chatbot;

class PhoneNormalizer
{
    public function normalize(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $normalized = preg_replace('/[^0-9]/', '', $phone);

        if (! $normalized) {
            return null;
        }

        if (str_starts_with($normalized, '00')) {
            $normalized = substr($normalized, 2);
        }

        if (str_starts_with($normalized, '39')) {
            return $normalized;
        }

        if (strlen($normalized) === 10 && str_starts_with($normalized, '3')) {
            return '39' . $normalized;
        }

        return $normalized;
    }
}
