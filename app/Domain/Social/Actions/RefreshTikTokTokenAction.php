<?php

namespace App\Domain\Social\Actions;

use App\Models\ClientSocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RefreshTikTokTokenAction
{
    public function execute(ClientSocialAccount $account): bool
    {
        if ($account->platform !== \App\Enums\Social\SocialPlatform::Tiktok) {
            return false;
        }

        if (!$account->refresh_token) {
            Log::warning('TikTok Refresh Token Missing', ['account_id' => $account->id]);
            return false;
        }

        $apiBase = config('services.tiktok.api_base', 'https://open.tiktokapis.com');

        $response = Http::asForm()->post("{$apiBase}/v2/oauth/token/", [
            'client_key' => config('services.tiktok.client_key'),
            'client_secret' => config('services.tiktok.client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->refresh_token,
        ]);

        if ($response->failed()) {
            Log::error('TikTok Refresh Token Failed', [
                'account_id' => $account->id,
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            // Aggiorniamo lo stato per segnalare che la connessione è compromessa (requires_reauth)
            $apiMetadata = $account->api_metadata ?? [];
            $apiMetadata['last_refresh_error'] = $response->json() ?? $response->body();

            $account->update([
                'api_status' => \App\Enums\Social\SocialApiStatus::Error,
                'api_notes' => 'Refresh token fallito o scaduto. Richiede nuova autenticazione manuale.',
                'last_api_error' => 'HTTP ' . $response->status() . ' - ' . substr($response->body(), 0, 100),
                'api_metadata' => $apiMetadata,
                'last_api_check_at' => now(),
            ]);

            return false;
        }

        $data = $response->json();

        $account->update([
            'access_token' => $data['access_token'] ?? $account->access_token,
            'refresh_token' => $data['refresh_token'] ?? $account->refresh_token,
            'token_expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : null,
            'tiktok_open_id' => $data['open_id'] ?? $account->tiktok_open_id,
            'api_status' => \App\Enums\Social\SocialApiStatus::Connected,
            'api_notes' => 'Token aggiornato con successo: ' . now()->toDateTimeString(),
            'last_api_check_at' => now(),
        ]);

        return true;
    }
}
