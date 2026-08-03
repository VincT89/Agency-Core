<?php

namespace App\Http\Controllers\Admin\Social;

use App\Domain\Social\Actions\SyncMetaAssetsAction;
use App\Enums\Social\AgencyConnectionStatus;
use App\Http\Controllers\Controller;
use App\Models\AgencySocialConnection;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class AgencyMetaOAuthController extends Controller
{
    private const OAUTH_SCOPES = [
        'pages_manage_posts',
        'pages_read_engagement',
        'pages_show_list',
        'business_management',
        'instagram_basic',
        'instagram_content_publish',
    ];

    public function redirect()
    {
        $configId = trim((string) config('services.meta.config_id'));

        if ($configId === '') {
            Log::warning('Agency Meta OAuth configuration missing', [
                'user_id' => auth()->id(),
            ]);

            return redirect()->route('admin.social.connections.index')
                ->with('error', 'Il collegamento Meta non è configurato.');
        }

        return $this->provider()
            ->setScopes(self::OAUTH_SCOPES)
            ->with(['config_id' => $configId])
            ->redirect();
    }

    public function callback(Request $request)
    {
        if ($request->has('error')) {
            Log::warning('Agency Meta OAuth Error', [
                'error' => (string) $request->query('error'),
                'user_id' => auth()->id(),
            ]);

            return redirect()->route('admin.social.connections.index')
                ->with('error', 'Meta ha annullato o rifiutato il collegamento.');
        }

        try {
            $socialUser = $this->provider()->user();
            $providerUserId = $socialUser->getId();
            $accessToken = $socialUser->token;

            if (
                ! is_string($providerUserId)
                || $providerUserId === ''
                || ! is_string($accessToken)
                || $accessToken === ''
            ) {
                throw new \UnexpectedValueException(
                    'Meta ha restituito una risposta OAuth incompleta.'
                );
            }

            $connection = AgencySocialConnection::updateOrCreate(
                [
                    'provider' => 'facebook',
                    'provider_user_id' => $providerUserId,
                ],
                [
                    'provider_user_name' => $socialUser->getName() ?? $socialUser->getNickname(),
                    'access_token' => $accessToken,
                    'refresh_token' => $socialUser->refreshToken,
                    'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
                    'last_token_refresh_at' => now(),
                    'requires_reauth' => false,
                    'status' => AgencyConnectionStatus::Connected,
                    'connected_by' => auth()->id(),
                    'connected_at' => now(),
                    'scopes' => $socialUser->approvedScopes ?? [],
                ]
            );

            // Appena connesso, lanciamo un primo sync degli asset
            $syncResult = app(
                SyncMetaAssetsAction::class
            )->execute($connection);

            return redirect()->route('admin.social.connections.index')
                ->with(
                    $syncResult->errors > 0 ? 'warning' : 'success',
                    $syncResult->errors > 0
                        ? 'Account Meta collegato, ma la sincronizzazione iniziale degli asset non è riuscita.'
                        : 'Account Meta collegato con successo.'
                );

        } catch (\Throwable $e) {
            Log::error('Agency Meta OAuth Exception', [
                'exception' => $e::class,
                'user_id' => auth()->id(),
            ]);

            return redirect()->route('admin.social.connections.index')
                ->with('error', 'Si è verificato un errore durante il collegamento con Meta.');
        }
    }

    private function provider()
    {
        $graphVersion = trim((string) config('services.meta.graph_version', 'v25.0'));

        return Socialite::driver('facebook')
            ->usingGraphVersion($graphVersion !== '' ? $graphVersion : 'v25.0')
            ->setHttpClient(
                new HttpClient([
                    'connect_timeout' => max(
                        1,
                        (int) config('services.meta.connect_timeout', 5)
                    ),
                    'timeout' => max(
                        1,
                        (int) config('services.meta.timeout', 15)
                    ),
                ])
            );
    }
}
