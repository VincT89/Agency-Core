<?php

namespace App\Support\Http;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

final class SocialProviderHttp
{
    public static function meta(bool $retrySafe = false): PendingRequest
    {
        return self::configured(
            'services.meta',
            $retrySafe
        );
    }

    public static function tiktok(bool $retrySafe = false): PendingRequest
    {
        return self::configured(
            'services.tiktok',
            $retrySafe
        );
    }

    private static function configured(
        string $configPrefix,
        bool $retrySafe
    ): PendingRequest {
        $request = Http::acceptJson()
            ->connectTimeout(max(1, (int) config("{$configPrefix}.connect_timeout", 5)))
            ->timeout(max(1, (int) config("{$configPrefix}.timeout", 20)))
            ->withOptions(['allow_redirects' => false]);

        return $retrySafe
            ? $request->retry(
                3,
                250,
                function (\Throwable $exception): bool {
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }

                    if ($exception instanceof RequestException && $exception->response) {
                        return in_array(
                            $exception->response->status(),
                            [408, 425, 429],
                            true
                        ) || $exception->response->serverError();
                    }

                    return false;
                },
                throw: false
            )
            : $request;
    }
}
