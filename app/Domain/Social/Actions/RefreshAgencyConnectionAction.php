<?php

namespace App\Domain\Social\Actions;

use App\Enums\Social\AgencyConnectionStatus;
use App\Models\AgencySocialConnection;
use App\Support\Http\ProviderErrorSanitizer;
use App\Support\Http\SocialProviderHttp;
use Illuminate\Support\Facades\Log;

class RefreshAgencyConnectionAction
{
    /**
     * Tenta il refresh del token long-lived per Meta
     */
    public function execute(AgencySocialConnection $connection): bool
    {
        if ($connection->provider !== 'facebook') {
            return false; // Per ora supportiamo solo Meta OAuth
        }

        if (! $connection->access_token) {
            return false;
        }

        try {
            $graphVersion = config('services.meta.graph_version', 'v19.0');
            $response = SocialProviderHttp::meta(retrySafe: true)
                ->get("https://graph.facebook.com/{$graphVersion}/oauth/access_token", [
                    'grant_type' => 'fb_exchange_token',
                    'client_id' => config('services.meta.client_id'),
                    'client_secret' => config('services.meta.client_secret'),
                    'fb_exchange_token' => $connection->access_token,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $newAccessToken = $data['access_token'] ?? null;

                if (! is_string($newAccessToken) || $newAccessToken === '') {
                    throw new \UnexpectedValueException(
                        'Meta non ha restituito un access token valido.'
                    );
                }

                $connection->update([
                    'access_token' => $newAccessToken,
                    'token_expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : null,
                    'last_token_refresh_at' => now(),
                    'requires_reauth' => false,
                    'status' => AgencyConnectionStatus::Connected,
                    'token_refresh_error' => null,
                    'last_api_error' => null,
                ]);

                // Ritiriamo anche giù i page token aggiornati
                app(SyncMetaAssetsAction::class)->execute($connection);

                return true;
            } else {
                $safeError = ProviderErrorSanitizer::message(
                    $response,
                    'Refresh token Meta fallito'
                );
                Log::warning('RefreshAgencyConnectionAction fallito', [
                    ...ProviderErrorSanitizer::context($response),
                    'id' => $connection->id,
                ]);

                // Se il token è scaduto o invalido, richiediamo reauth
                $errorData = $response->json();
                if (isset($errorData['error']['code']) && in_array($errorData['error']['code'], [190, 102])) {
                    $connection->update([
                        'requires_reauth' => true,
                        'status' => AgencyConnectionStatus::Expired,
                        'last_api_error' => $safeError,
                        'token_refresh_error' => $safeError,
                    ]);
                } else {
                    $connection->update([
                        'last_api_error' => $safeError,
                        'token_refresh_error' => $safeError,
                    ]);
                }

                return false;
            }
        } catch (\Throwable $e) {
            Log::error('RefreshAgencyConnectionAction eccezione', [
                'exception' => $e::class,
                'id' => $connection->id,
            ]);
            $connection->update([
                'last_api_error' => 'Errore interno o di rete durante il refresh Meta.',
                'token_refresh_error' => 'Errore interno o di rete durante il refresh Meta.',
            ]);

            return false;
        }
    }
}
