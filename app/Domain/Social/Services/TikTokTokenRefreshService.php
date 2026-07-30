<?php

namespace App\Domain\Social\Services;

use App\Models\ClientSocialAccount;
use App\Support\Http\ProviderErrorSanitizer;
use App\Support\Http\SocialProviderHttp;
use Illuminate\Support\Facades\Log;

class TikTokTokenRefreshService
{
    /**
     * Verifica se il token è scaduto (o sta per scadere) ed eventualmente effettua il refresh.
     * Restituisce true se il token è valido o è stato refreshato con successo, false altrimenti.
     */
    public function ensureValidToken(ClientSocialAccount $account): bool
    {
        if ($account->platform->value !== 'tiktok') {
            return false;
        }

        // Se non abbiamo l'token_expires_at, lo consideriamo "a rischio" o proviamo a usarlo
        if (! $account->token_expires_at) {
            return true;
        }

        // Se scade entro 5 minuti (o è già scaduto)
        if ($account->token_expires_at->isBefore(now()->addMinutes(5))) {
            return $this->refreshToken($account);
        }

        return true;
    }

    private function refreshToken(ClientSocialAccount $account): bool
    {
        if (! $account->refresh_token) {
            Log::warning('TikTokTokenRefreshService: impossibile fare refresh, refresh_token mancante.', ['account_id' => $account->id]);

            return false;
        }

        $clientKey = config('services.tiktok.client_key');
        $clientSecret = config('services.tiktok.client_secret');
        $apiBase = config('services.tiktok.api_base', 'https://open.tiktokapis.com');

        try {
            $response = SocialProviderHttp::tiktok()->asForm()->post("{$apiBase}/v2/oauth/token/", [
                'client_key' => $clientKey,
                'client_secret' => $clientSecret,
                'grant_type' => 'refresh_token',
                'refresh_token' => $account->refresh_token,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $newAccessToken = $data['access_token'] ?? null;

                if (! is_string($newAccessToken) || $newAccessToken === '') {
                    Log::error(
                        'TikTokTokenRefreshService: risposta priva di access token.',
                        ['account_id' => $account->id]
                    );

                    return false;
                }

                $account->update([
                    'access_token' => $newAccessToken,
                    'refresh_token' => $data['refresh_token'] ?? $account->refresh_token,
                    'token_expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : $account->token_expires_at,
                    'refresh_token_expires_at' => isset($data['refresh_expires_in']) ? now()->addSeconds($data['refresh_expires_in']) : null,
                ]);

                Log::info('TikTokTokenRefreshService: Token aggiornato con successo.', ['account_id' => $account->id]);

                return true;
            }

            Log::error('TikTokTokenRefreshService: Fallito refresh token', [
                'account_id' => $account->id,
                ...ProviderErrorSanitizer::context($response),
            ]);

            return false;

        } catch (\Throwable $e) {
            Log::error('TikTokTokenRefreshService: Eccezione durante refresh token', [
                'account_id' => $account->id,
                'exception' => $e::class,
            ]);

            return false;
        }
    }
}
