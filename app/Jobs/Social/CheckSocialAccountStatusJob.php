<?php

namespace App\Jobs\Social;

use App\Enums\Social\SocialApiStatus;
use App\Models\ClientSocialAccount;
use App\Support\Http\ProviderErrorSanitizer;
use App\Support\Http\SocialProviderHttp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckSocialAccountStatusJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 300;

    public int $timeout = 60;

    public int $tries = 3;

    public function __construct(
        public int $clientSocialAccountId
    ) {
        $this->onQueue('social-publishing');
    }

    public function uniqueId(): string
    {
        return (string) $this->clientSocialAccountId;
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(): void
    {
        $account = ClientSocialAccount::find($this->clientSocialAccountId);

        if (
            ! $account
            || ! $account->isMetaPlatform()
            || blank($account->access_token)
        ) {
            return;
        }

        $graphVersion = (string) config(
            'services.meta.graph_version',
            'v23.0'
        );

        try {
            $response = SocialProviderHttp::meta(retrySafe: true)
                ->withToken($account->access_token)
                ->get(
                    "https://graph.facebook.com/{$graphVersion}/me",
                    ['fields' => 'id,name']
                );
        } catch (ConnectionException $e) {
            $account->update([
                'api_status' => SocialApiStatus::TemporaryFailure,
                'last_api_check_at' => now(),
                'last_api_error' => 'Connessione Meta temporaneamente non disponibile.',
            ]);
            Log::warning('Timeout durante controllo account Meta', [
                'account_id' => $account->id,
                'exception' => $e::class,
            ]);

            throw $e;
        }

        if ($response->successful()) {
            $account->update([
                'api_status' => SocialApiStatus::Connected,
                'last_api_check_at' => now(),
                'last_api_error' => null,
            ]);

            return;
        }

        $error = $response->json('error');
        $error = is_array($error) ? $error : [];
        $errorCode = isset($error['code']) ? (int) $error['code'] : null;
        $safeError = ProviderErrorSanitizer::message(
            $response,
            'Controllo account Meta fallito'
        );

        if (
            ($error['type'] ?? null) === 'OAuthException'
            && $errorCode === 190
        ) {
            $account->update([
                'api_status' => SocialApiStatus::Disconnected,
                'is_ready_to_publish' => false,
                'last_api_check_at' => now(),
                'last_api_error' => 'Token Meta non valido o revocato.',
            ]);
            Log::warning('Token Meta non valido o revocato', [
                'account_id' => $account->id,
                'provider_code' => $errorCode,
            ]);

            return;
        }

        if (
            in_array($response->status(), [408, 425, 429], true)
            || $response->serverError()
            || in_array($errorCode, [4, 17, 32, 613], true)
        ) {
            $account->update([
                'api_status' => SocialApiStatus::TemporaryFailure,
                'last_api_check_at' => now(),
                'last_api_error' => $safeError,
            ]);
            Log::warning('Errore temporaneo durante controllo account Meta', [
                'account_id' => $account->id,
                'provider_code' => $errorCode,
                ...ProviderErrorSanitizer::context($response),
            ]);

            return;
        }

        $account->update([
            'api_status' => SocialApiStatus::Error,
            'last_api_check_at' => now(),
            'last_api_error' => $safeError,
        ]);
        Log::warning('Errore durante controllo account Meta', [
            'account_id' => $account->id,
            'provider_code' => $errorCode,
            ...ProviderErrorSanitizer::context($response),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $account = ClientSocialAccount::find($this->clientSocialAccountId);

        $account?->update([
            'api_status' => SocialApiStatus::Error,
            'last_api_check_at' => now(),
            'last_api_error' => 'Controllo account Meta non completato.',
        ]);

        Log::error('Controllo account Meta fallito definitivamente', [
            'account_id' => $this->clientSocialAccountId,
            'exception' => $exception::class,
        ]);
    }
}
