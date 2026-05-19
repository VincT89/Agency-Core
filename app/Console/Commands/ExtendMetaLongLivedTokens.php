<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ExtendMetaLongLivedTokens extends Command
{
    protected $signature = 'social:extend-tokens';
    protected $description = 'Estende i token Meta di breve durata (stub)';

    public function handle()
    {
        $this->info("Inizio verifica ed estensione token Meta long-lived.");

        $clientId = config('services.meta.client_id');
        $clientSecret = config('services.meta.client_secret');

        if (!$clientId || !$clientSecret) {
            $this->error("Credenziali Meta non configurate in services.meta");
            return;
        }

        // Cerchiamo gli account Meta OAuth connessi i cui token scadono entro i prossimi 10 giorni
        $accounts = \App\Models\ClientSocialAccount::where('connection_mode', \App\Enums\Social\SocialConnectionMode::Oauth)
            ->whereIn('platform', [\App\Enums\Social\SocialPlatform::Facebook, \App\Enums\Social\SocialPlatform::Instagram])
            ->whereNotNull('access_token')
            ->where('api_status', \App\Enums\Social\SocialApiStatus::Connected)
            ->where(function($q) {
                $q->whereNull('token_expires_at')
                  ->orWhere('token_expires_at', '<=', now()->addDays(10));
            })
            ->get();

        if ($accounts->isEmpty()) {
            $this->info("Nessun token Meta richiede estensione al momento.");
            return;
        }

        foreach ($accounts as $account) {
            $this->info("Estensione token per account ID: {$account->id} ({$account->platform->value})");

            try {
                $response = \Illuminate\Support\Facades\Http::timeout(15)->get('https://graph.facebook.com/v19.0/oauth/access_token', [
                    'grant_type' => 'fb_exchange_token',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'fb_exchange_token' => $account->access_token,
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    if (!empty($data['access_token'])) {
                        $expiresIn = $data['expires_in'] ?? null;
                        
                        $account->update([
                            'access_token' => $data['access_token'],
                            'token_expires_at' => $expiresIn ? now()->addSeconds((int) $expiresIn) : null,
                            'last_api_check_at' => now(),
                        ]);
                        
                        $this->info("Token esteso con successo per l'account {$account->id}");
                    } else {
                        $this->warn("Risposta positiva ma senza access_token per account {$account->id}");
                    }
                } else {
                    $error = $response->json('error');
                    $this->error("Fallita estensione token per account {$account->id}: " . ($error['message'] ?? $response->body()));
                    
                    \Illuminate\Support\Facades\Log::warning("Fallita estensione token per account {$account->id}", [
                        'response' => $response->json(),
                    ]);
                }
            } catch (\Exception $e) {
                $this->error("Eccezione durante l'estensione per account {$account->id}: " . $e->getMessage());
                \Illuminate\Support\Facades\Log::error("Eccezione estensione token account {$account->id}: " . $e->getMessage());
            }
        }
        
        $this->info("Estensione token Meta completata.");
    }
}
