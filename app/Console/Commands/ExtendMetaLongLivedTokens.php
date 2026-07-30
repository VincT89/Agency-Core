<?php

namespace App\Console\Commands;

use App\Enums\Social\SocialApiStatus;
use App\Enums\Social\SocialConnectionMode;
use App\Enums\Social\SocialPlatform;
use App\Models\ClientSocialAccount;
use App\Support\Http\ProviderErrorSanitizer;
use App\Support\Http\SocialProviderHttp;
use App\Support\Monitoring\TracksSystemCommandRuns;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExtendMetaLongLivedTokens extends Command
{
    use TracksSystemCommandRuns;

    protected $signature = 'social:extend-tokens';

    protected $description = 'Estende in sicurezza i token Meta prossimi alla scadenza';

    public function handle(): int
    {
        return $this->runTracked($this->getName(), function (): int {
            $this->info('Inizio verifica ed estensione token Meta long-lived.');

            $clientId = (string) config('services.meta.client_id', '');
            $clientSecret = (string) config('services.meta.client_secret', '');

            if ($clientId === '' || $clientSecret === '') {
                $this->error('Credenziali Meta non configurate in services.meta.');

                return self::FAILURE;
            }

            $accounts = ClientSocialAccount::query()
                ->where('connection_mode', SocialConnectionMode::Oauth)
                ->whereIn('platform', [
                    SocialPlatform::Facebook,
                    SocialPlatform::Instagram,
                ])
                ->whereNotNull('access_token')
                ->where('api_status', SocialApiStatus::Connected)
                ->where(function ($query): void {
                    $query
                        ->whereNull('token_expires_at')
                        ->orWhere(
                            'token_expires_at',
                            '<=',
                            now()->addDays(10)
                        );
                })
                ->get();

            if ($accounts->isEmpty()) {
                $this->info('Nessun token Meta richiede estensione al momento.');

                return self::SUCCESS;
            }

            $failures = 0;
            $graphVersion = (string) config(
                'services.meta.graph_version',
                'v23.0'
            );

            foreach ($accounts as $account) {
                $this->info(
                    "Estensione token per account ID: {$account->id} "
                    ."({$account->platform->value})"
                );

                try {
                    $response = SocialProviderHttp::meta(retrySafe: true)
                        ->get(
                            "https://graph.facebook.com/{$graphVersion}/oauth/access_token",
                            [
                                'grant_type' => 'fb_exchange_token',
                                'client_id' => $clientId,
                                'client_secret' => $clientSecret,
                                'fb_exchange_token' => $account->access_token,
                            ]
                        );

                    if (! $response->successful()) {
                        $failures++;
                        $safeError = ProviderErrorSanitizer::message(
                            $response,
                            'Estensione token Meta fallita'
                        );
                        $this->error(
                            "Fallita estensione token per account {$account->id}: "
                            .$safeError
                        );
                        $account->update([
                            'last_api_check_at' => now(),
                            'last_api_error' => $safeError,
                        ]);
                        Log::warning('Estensione token Meta fallita', [
                            'account_id' => $account->id,
                            ...ProviderErrorSanitizer::context($response),
                        ]);

                        continue;
                    }

                    $data = $response->json();
                    $accessToken = is_array($data)
                        ? ($data['access_token'] ?? null)
                        : null;

                    if (! is_string($accessToken) || $accessToken === '') {
                        $failures++;
                        $safeError = 'Risposta Meta priva di access_token.';
                        $this->warn(
                            "{$safeError} Account {$account->id}."
                        );
                        $account->update([
                            'last_api_check_at' => now(),
                            'last_api_error' => $safeError,
                        ]);

                        continue;
                    }

                    $expiresIn = is_array($data)
                        ? ($data['expires_in'] ?? null)
                        : null;

                    $account->update([
                        'access_token' => $accessToken,
                        'token_expires_at' => is_numeric($expiresIn)
                            ? now()->addSeconds((int) $expiresIn)
                            : $account->token_expires_at,
                        'last_api_check_at' => now(),
                        'last_api_error' => null,
                    ]);

                    $this->info(
                        "Token esteso con successo per l'account {$account->id}."
                    );
                } catch (\Throwable $e) {
                    $failures++;
                    $this->error(
                        "Estensione non riuscita per account {$account->id}."
                    );
                    Log::error('Eccezione durante estensione token Meta', [
                        'account_id' => $account->id,
                        'exception' => $e::class,
                    ]);
                }
            }

            if ($failures > 0) {
                $this->error(
                    "Estensione token Meta completata con {$failures} errori."
                );

                return self::FAILURE;
            }

            $this->info('Estensione token Meta completata.');

            return self::SUCCESS;
        });
    }
}
