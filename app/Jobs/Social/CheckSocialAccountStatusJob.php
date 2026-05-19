<?php

namespace App\Jobs\Social;

use App\Models\ClientSocialAccount;
use App\Enums\Social\SocialApiStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use Illuminate\Contracts\Queue\ShouldBeUnique;

class CheckSocialAccountStatusJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $uniqueFor = 300;

    public function __construct(
        public int $clientSocialAccountId
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->clientSocialAccountId;
    }

    public function handle(): void
    {
        $account = ClientSocialAccount::find($this->clientSocialAccountId);

        if (!$account || !$account->isMetaPlatform() || empty($account->access_token)) {
            return;
        }

        try {
            // Verify token validity by calling /me endpoint
            $response = Http::timeout(10)->get("https://graph.facebook.com/v21.0/me", [
                'access_token' => $account->access_token,
                'fields' => 'id,name'
            ]);

            if ($response->successful()) {
                $account->update([
                    'api_status' => SocialApiStatus::Connected,
                    'last_api_check_at' => now(),
                    'last_api_error' => null,
                ]);
            } else {
                $error = $response->json('error');
                
                // If it's an OAuthException and code is 190 (Invalid OAuth 2.0 Access Token), 
                // the token is expired, revoked or invalid.
                if (isset($error['type']) && $error['type'] === 'OAuthException' && isset($error['code']) && $error['code'] == 190) {
                    $account->update([
                        'api_status' => SocialApiStatus::Disconnected,
                        'is_ready_to_publish' => false,
                        'last_api_check_at' => now(),
                        'last_api_error' => 'Token invalido o revocato',
                    ]);
                    
                    Log::warning("Token revoked for Social Account {$account->id}: " . ($error['message'] ?? 'Unknown error'));
                } elseif (isset($error['code']) && in_array($error['code'], [4, 17, 32, 613])) {
                    $account->update([
                        'api_status' => SocialApiStatus::TemporaryFailure,
                        'last_api_check_at' => now(),
                        'last_api_error' => 'Rate limit o blocco temporaneo Meta (' . ($error['message'] ?? 'Sconosciuto') . ')',
                    ]);
                    Log::warning("Temporary failure (Rate limit) for Social Account {$account->id}: " . ($error['message'] ?? 'Unknown error'));
                } else {
                    // Temporary or other API error
                    $account->update([
                        'api_status' => SocialApiStatus::Error,
                        'last_api_check_at' => now(),
                        'last_api_error' => $error['message'] ?? 'Errore API sconosciuto',
                    ]);
                }
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning("Timeout checking social account status for ID {$this->clientSocialAccountId}: " . $e->getMessage());
            
            $account->update([
                'api_status' => SocialApiStatus::Error,
                'last_api_check_at' => now(),
                'last_api_error' => 'Timeout connessione Meta API',
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to check social account status for ID {$this->clientSocialAccountId}: " . $e->getMessage());
            
            $account->update([
                'api_status' => SocialApiStatus::Error,
                'last_api_check_at' => now(),
                'last_api_error' => 'Errore di connessione API',
            ]);
        }
    }
}
