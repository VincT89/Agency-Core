<?php

namespace App\Services\Integrations\Aruba;

use App\Exceptions\Finance\ArubaApiException;
use App\Models\IntegrationLog;
use App\Support\Http\ProviderErrorSanitizer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Throwable;

class ArubaAuthenticator
{
    public function __construct(
        private readonly ArubaConfiguration $configuration,
    ) {}

    public function token(): string
    {
        $this->configuration->assertCanConnect();

        if ($token = $this->cachedAccessToken()) {
            return $token;
        }

        $lock = Cache::lock($this->cacheKey().':lock', 15);

        try {
            return $lock->block(5, function (): string {
                if ($token = $this->cachedAccessToken()) {
                    return $token;
                }

                $cached = Cache::get($this->cacheKey());

                if (
                    is_array($cached)
                    && ($cached['refresh_until'] ?? 0) > now()->timestamp
                    && filled($cached['refresh_token'] ?? null)
                ) {
                    return $this->authenticate([
                        'grant_type' => 'refresh_token',
                        'refresh_token' => Crypt::decryptString($cached['refresh_token']),
                    ]);
                }

                return $this->authenticate([
                    'grant_type' => 'password',
                    'username' => $this->configuration->username(),
                    'password' => $this->configuration->password(),
                ]);
            });
        } catch (ArubaApiException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new ArubaApiException(
                'Il collegamento Aruba è temporaneamente occupato. Riprova tra poco.',
                previous: $exception,
            );
        }
    }

    public function forget(): void
    {
        Cache::forget($this->cacheKey());
    }

    private function cachedAccessToken(): ?string
    {
        $cached = Cache::get($this->cacheKey());

        if (
            ! is_array($cached)
            || ($cached['access_until'] ?? 0) <= now()->timestamp
            || blank($cached['access_token'] ?? null)
        ) {
            return null;
        }

        try {
            return Crypt::decryptString($cached['access_token']);
        } catch (Throwable) {
            $this->forget();

            return null;
        }
    }

    /**
     * @param  array<string, string>  $credentials
     */
    private function authenticate(array $credentials): string
    {
        $endpoint = $this->configuration->authBaseUrl().'/auth/signin';
        $log = IntegrationLog::create([
            'provider' => 'aruba',
            'direction' => 'outbound',
            'endpoint' => $endpoint,
            'event' => 'electronic_invoice_authentication',
            'payload' => ['grant_type' => $credentials['grant_type']],
            'status' => 'processing',
        ]);

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->connectTimeout($this->configuration->connectTimeout())
                ->timeout($this->configuration->timeout())
                ->withOptions(['allow_redirects' => false])
                ->post($endpoint, $credentials);

            $log->update([
                'status_code' => $response->status(),
                'response' => ProviderErrorSanitizer::payload($response),
                'status' => $response->successful() ? 'processed' : 'failed',
                'processed_at' => now(),
            ]);

            if (! $response->successful()) {
                $this->forget();

                throw $this->authenticationException($response);
            }

            $payload = $response->json();
            $accessToken = is_array($payload) ? ($payload['access_token'] ?? null) : null;
            $refreshToken = is_array($payload) ? ($payload['refresh_token'] ?? null) : null;
            $expiresIn = is_array($payload) ? (int) ($payload['expires_in'] ?? 1800) : 1800;

            if (! is_string($accessToken) || $accessToken === '') {
                throw new ArubaApiException(
                    'Aruba non ha restituito una sessione valida. Riprova tra un minuto.'
                );
            }

            Cache::put($this->cacheKey(), [
                'access_token' => Crypt::encryptString($accessToken),
                'refresh_token' => is_string($refreshToken) && $refreshToken !== ''
                    ? Crypt::encryptString($refreshToken)
                    : null,
                'access_until' => now()->addSeconds(max(60, $expiresIn - 300))->timestamp,
                'refresh_until' => now()->addMinutes(55)->timestamp,
            ], now()->addMinutes(60));

            return $accessToken;
        } catch (ArubaApiException $exception) {
            $log->update([
                'status' => 'failed',
                'error_message' => $exception->userMessage,
                'processed_at' => now(),
            ]);

            throw $exception;
        } catch (ConnectionException $exception) {
            $message = 'Aruba non è raggiungibile in questo momento. Riprova tra poco.';
            $log->update([
                'status' => 'failed',
                'error_message' => $message,
                'processed_at' => now(),
            ]);

            throw new ArubaApiException($message, previous: $exception);
        } catch (Throwable $exception) {
            $message = ProviderErrorSanitizer::safeText($exception->getMessage());
            $log->update([
                'status' => 'failed',
                'error_message' => $message,
                'processed_at' => now(),
            ]);

            throw new ArubaApiException(
                'Non è stato possibile aprire una sessione con Aruba.',
                previous: $exception,
            );
        }
    }

    private function authenticationException(Response $response): ArubaApiException
    {
        $payload = $response->json();
        $description = is_array($payload)
            ? strtolower((string) ($payload['error_description'] ?? ''))
            : '';

        $message = match (true) {
            $response->status() === 429 => 'Aruba consente una sola autenticazione al minuto. Attendi un minuto e riprova.',
            str_contains($description, 'incorrect'),
            str_contains($description, 'invalid_grant') => 'Le credenziali Aruba non sono valide.',
            default => 'Aruba non ha autorizzato il collegamento.',
        };

        return new ArubaApiException(
            $message,
            providerCode: is_array($payload) ? (string) ($payload['error'] ?? '') : null,
            responsePayload: is_array($payload) ? ProviderErrorSanitizer::payload($response) : null,
            httpStatus: $response->status(),
        );
    }

    private function cacheKey(): string
    {
        return 'aruba:e-invoicing:token:'
            .$this->configuration->environment().':'
            .hash('sha256', strtolower($this->configuration->username()));
    }
}
