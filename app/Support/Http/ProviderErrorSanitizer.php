<?php

namespace App\Support\Http;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;

final class ProviderErrorSanitizer
{
    public static function message(
        Response $response,
        string $prefix = 'Errore del provider'
    ): string {
        $payload = $response->json();
        $providerMessage = is_array($payload)
            ? data_get($payload, 'error.message')
                ?? data_get($payload, 'message')
                ?? data_get($payload, 'error_description')
            : null;
        $providerCode = is_array($payload)
            ? data_get($payload, 'error.code') ?? data_get($payload, 'code')
            : null;

        $message = "{$prefix} (HTTP {$response->status()})";

        if (is_scalar($providerCode) && (string) $providerCode !== '') {
            $message .= ' ['.(string) $providerCode.']';
        }

        if (is_string($providerMessage) && $providerMessage !== '') {
            $message .= ': '.$providerMessage;
        }

        return self::safeText($message);
    }

    public static function context(Response $response): array
    {
        return [
            'status' => $response->status(),
            'error' => self::message($response),
        ];
    }

    public static function payload(Response $response): ?array
    {
        $payload = $response->json();

        return is_array($payload)
            ? self::withoutSecrets($payload)
            : null;
    }

    public static function safeText(string $message): string
    {
        $redacted = preg_replace(
            [
                '/(https?:\/\/[^\s?#]+)\?[^\s]+/i',
                '/(\b(?:access_token|refresh_token|fb_exchange_token|client_secret|app_secret|signing_secret|api_key|password|code|signature|key)=)[^&\s]+/i',
                '/("(?:access_token|refresh_token|fb_exchange_token|client_secret|app_secret|signing_secret|api_key|password|code|signature|key)"\s*:\s*")[^"]+/i',
                '/(Bearer\s+)[A-Za-z0-9._~+\/=-]+/i',
            ],
            [
                '$1?[REDACTED]',
                '$1[REDACTED]',
                '$1[REDACTED]',
                '$1[REDACTED]',
            ],
            strip_tags($message)
        ) ?? 'Errore del provider.';

        $redacted = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $redacted)
            ?? 'Errore del provider.';

        return Str::limit(trim($redacted), 1000, '');
    }

    private static function withoutSecrets(array $payload): array
    {
        $sensitiveKeys = [
            'access_token',
            'refresh_token',
            'fb_exchange_token',
            'client_secret',
            'app_secret',
            'signing_secret',
            'api_key',
            'password',
            'authorization',
            'code',
            'signature',
            'key',
        ];

        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), $sensitiveKeys, true)) {
                $payload[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $payload[$key] = self::withoutSecrets($value);
            }
        }

        return $payload;
    }
}
