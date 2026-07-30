<?php

namespace App\Domain\Social\Actions;

use App\Enums\Social\SocialApiStatus;
use App\Enums\Social\SocialPlatform;
use App\Models\ClientSocialAccount;
use App\Support\Http\ProviderErrorSanitizer;
use App\Support\Http\SocialProviderHttp;
use Illuminate\Support\Facades\Log;

class RefreshTikTokTokenAction
{
    public function execute(ClientSocialAccount $account): bool
    {
        if ($account->platform !== SocialPlatform::Tiktok) {
            return false;
        }

        if (! $account->refresh_token) {
            Log::warning('TikTok Refresh Token Missing', ['account_id' => $account->id]);

            return false;
        }

        $apiBase = config('services.tiktok.api_base', 'https://open.tiktokapis.com');

        try {
            $response = SocialProviderHttp::tiktok()->asForm()->post("{$apiBase}/v2/oauth/token/", [
                'client_key' => config('services.tiktok.client_key'),
                'client_secret' => config('services.tiktok.client_secret'),
                'grant_type' => 'refresh_token',
                'refresh_token' => $account->refresh_token,
            ]);
        } catch (\Throwable $e) {
            Log::error('TikTok Refresh Token Exception', [
                'account_id' => $account->id,
                'exception' => $e::class,
            ]);
            $account->update([
                'api_status' => SocialApiStatus::Error,
                'api_notes' => 'Errore di rete durante il refresh del token.',
                'last_api_error' => 'Errore di rete durante il refresh TikTok.',
                'last_api_check_at' => now(),
            ]);

            return false;
        }

        if ($response->failed()) {
            $safeError = ProviderErrorSanitizer::message(
                $response,
                'Refresh token TikTok fallito'
            );
            Log::error('TikTok Refresh Token Failed', [
                'account_id' => $account->id,
                ...ProviderErrorSanitizer::context($response),
            ]);

            // Aggiorniamo lo stato per segnalare che la connessione è compromessa (requires_reauth)
            $apiMetadata = $account->api_metadata ?? [];
            $apiMetadata['last_refresh_error'] = [
                'status' => $response->status(),
                'message' => $safeError,
            ];

            $account->update([
                'api_status' => SocialApiStatus::Error,
                'api_notes' => 'Refresh token fallito o scaduto. Richiede nuova autenticazione manuale.',
                'last_api_error' => $safeError,
                'api_metadata' => $apiMetadata,
                'last_api_check_at' => now(),
            ]);

            return false;
        }

        $data = $response->json();
        $newAccessToken = $data['access_token'] ?? null;

        if (! is_string($newAccessToken) || $newAccessToken === '') {
            $account->update([
                'api_status' => SocialApiStatus::Error,
                'api_notes' => 'TikTok non ha restituito un access token valido.',
                'last_api_error' => 'Risposta refresh TikTok priva di access token.',
                'last_api_check_at' => now(),
            ]);

            return false;
        }

        $account->update([
            'access_token' => $newAccessToken,
            'refresh_token' => $data['refresh_token'] ?? $account->refresh_token,
            'token_expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : null,
            'tiktok_open_id' => $data['open_id'] ?? $account->tiktok_open_id,
            'api_status' => SocialApiStatus::Connected,
            'api_notes' => 'Token aggiornato con successo: '.now()->toDateTimeString(),
            'last_api_check_at' => now(),
        ]);

        return true;
    }
}
