<?php

namespace App\Domain\Social\Actions;

use App\Models\AgencySocialConnection;
use App\Enums\Social\AgencyConnectionStatus;
use Illuminate\Support\Facades\Http;
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

        if (!$connection->access_token) {
            return false;
        }

        try {
            $graphVersion = config('services.meta.graph_version', 'v19.0');
            $response = Http::get("https://graph.facebook.com/{$graphVersion}/oauth/access_token", [
                'grant_type' => 'fb_exchange_token',
                'client_id' => config('services.meta.client_id'),
                'client_secret' => config('services.meta.client_secret'),
                'fb_exchange_token' => $connection->access_token
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                $connection->update([
                    'access_token' => $data['access_token'],
                    'token_expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : null,
                    'last_token_refresh_at' => now(),
                    'requires_reauth' => false,
                    'status' => AgencyConnectionStatus::Connected,
                ]);

                // Ritiriamo anche giù i page token aggiornati
                app(SyncMetaAssetsAction::class)->execute($connection);

                return true;
            } else {
                Log::warning("RefreshAgencyConnectionAction fallito", ['response' => $response->body(), 'id' => $connection->id]);
                
                // Se il token è scaduto o invalido, richiediamo reauth
                $errorData = $response->json();
                if (isset($errorData['error']['code']) && in_array($errorData['error']['code'], [190, 102])) {
                    $connection->update([
                        'requires_reauth' => true,
                        'status' => AgencyConnectionStatus::Expired,
                    ]);
                }
                
                return false;
            }
        } catch (\Exception $e) {
            Log::error("RefreshAgencyConnectionAction eccezione", ['error' => $e->getMessage(), 'id' => $connection->id]);
            return false;
        }
    }
}
