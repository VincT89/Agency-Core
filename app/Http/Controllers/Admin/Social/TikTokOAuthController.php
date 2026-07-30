<?php

namespace App\Http\Controllers\Admin\Social;

use App\Domain\Social\TikTok\TikTokCreatorInfoService;
use App\Enums\Social\SocialPlatform;
use App\Http\Controllers\Controller;
use App\Models\ClientSocialAccount;
use App\Support\Http\ProviderErrorSanitizer;
use App\Support\Http\SocialProviderHttp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TikTokOAuthController extends Controller
{
    public function redirect(Request $request)
    {
        $accountId = session('tiktok_oauth_account_id');
        $clientId = session('tiktok_oauth_client_id');
        $expectedPlatform = session('tiktok_oauth_expected_platform');

        if (! $accountId || ! $clientId || $expectedPlatform !== SocialPlatform::Tiktok->value) {
            return redirect()->route('clients.index')->with('error', 'Sessione OAuth non inizializzata o contesto invalido. Riprova dal pannello del cliente.');
        }

        $account = ClientSocialAccount::findOrFail($accountId);

        // Blocco sicurezza tenant/platform
        if ($account->client_id !== $clientId || $account->platform !== SocialPlatform::Tiktok) {
            return redirect()->route('clients.show', $account->client_id)
                ->with('error', 'Incoerenza tenant/platform rilevata. Azione annullata.');
        }

        $clientKey = config('services.tiktok.client_key');
        $redirectUri = config('services.tiktok.redirect_uri');

        $state = Str::random(40);
        session(['tiktok_oauth_state' => $state]);

        $scopes = ['user.info.basic'];
        $publishMode = config('services.tiktok.delivery_mode', 'disabled');

        if ($publishMode === 'draft') {
            $scopes[] = 'video.upload';
        } elseif ($publishMode === 'direct') {
            if (! config('services.tiktok.direct_publish_enabled', false)) {
                return redirect()->route('clients.show', $account->client_id)
                    ->with('error', 'La modalità direct publish (video.publish) è disabilitata temporaneamente. Impostare delivery_mode=draft.');
            }
            $scopes[] = 'video.publish';
        }

        $query = http_build_query([
            'client_key' => $clientKey,
            'response_type' => 'code',
            'scope' => implode(',', $scopes),
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);

        return redirect("https://www.tiktok.com/v2/auth/authorize/?{$query}");
    }

    public function callback(Request $request)
    {
        $state = $request->query('state');
        $code = $request->query('code');
        $error = $request->query('error');
        $errorDescription = $request->query('error_description');

        $sessionState = session()->pull('tiktok_oauth_state');
        $accountId = session()->pull('tiktok_oauth_account_id');
        $clientId = session()->pull('tiktok_oauth_client_id');
        $expectedPlatform = session()->pull('tiktok_oauth_expected_platform');

        if (! $accountId || ! $clientId || $expectedPlatform !== SocialPlatform::Tiktok->value) {
            return redirect()->route('clients.index')->with('error', 'Sessione OAuth scaduta o già utilizzata (possibile doppio click o multi-tab).');
        }

        $account = ClientSocialAccount::findOrFail($accountId);

        // Validazione cross-tab del tenant e platform
        if ($account->client_id !== $clientId || $account->platform !== SocialPlatform::Tiktok) {
            return redirect()->route('clients.show', $account->client_id)
                ->with('error', 'Incoerenza tenant/platform rilevata nel callback. Connessione annullata.');
        }

        if ($error) {
            Log::warning('TikTok OAuth Error', [
                'error' => ProviderErrorSanitizer::safeText((string) $error),
                'account_id' => $account->id,
            ]);

            return redirect()->route('clients.show', $account->client_id)
                ->with('error', 'TikTok ha annullato o rifiutato il collegamento.');
        }

        if (! $state || $state !== $sessionState) {
            return redirect()->route('clients.show', $account->client_id)
                ->with('error', 'Stato OAuth non valido o scaduto. Riprova.');
        }

        if (! $code) {
            return redirect()->route('clients.show', $account->client_id)
                ->with('error', 'Codice di autorizzazione non ricevuto da TikTok.');
        }

        // Scambio del codice per l'access token
        $apiBase = config('services.tiktok.api_base', 'https://open.tiktokapis.com');

        try {
            $response = SocialProviderHttp::tiktok()->asForm()->post("{$apiBase}/v2/oauth/token/", [
                'client_key' => config('services.tiktok.client_key'),
                'client_secret' => config('services.tiktok.client_secret'),
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => config('services.tiktok.redirect_uri'),
            ]);
        } catch (\Throwable $e) {
            Log::error('TikTok Token Exchange Exception', [
                'account_id' => $account->id,
                'exception' => $e::class,
            ]);

            return redirect()->route('clients.show', $account->client_id)
                ->with('error', 'TikTok non è raggiungibile. Riprova più tardi.');
        }

        if ($response->failed()) {
            Log::error('TikTok Token Exchange Error', [
                'account_id' => $account->id,
                ...ProviderErrorSanitizer::context($response),
            ]);

            return redirect()->route('clients.show', $account->client_id)
                ->with('error', 'Impossibile completare lo scambio del token con TikTok.');
        }

        $data = $response->json();
        $accessToken = $data['access_token'] ?? null;
        $openId = $data['open_id'] ?? null;

        if (
            ! is_string($accessToken)
            || $accessToken === ''
            || ! is_string($openId)
            || $openId === ''
        ) {
            Log::error('TikTok Token Exchange Invalid Response', [
                'account_id' => $account->id,
                'has_access_token' => filled($accessToken),
                'has_open_id' => filled($openId),
            ]);

            return redirect()->route('clients.show', $account->client_id)
                ->with('error', 'TikTok ha restituito una risposta incompleta. Nessun dato è stato salvato.');
        }

        // Salva i token e le informazioni dell'account
        $account->update([
            'access_token' => $accessToken,
            'refresh_token' => $data['refresh_token'] ?? null,
            'token_expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : null,
            'refresh_token_expires_at' => isset($data['refresh_expires_in']) ? now()->addSeconds($data['refresh_expires_in']) : null,
            'tiktok_open_id' => $openId,
            'provider_account_id' => $openId,
            'scopes' => array_values(array_filter(explode(',', $data['scope'] ?? ''))),
            'api_status' => 'connected',
            'connected_at' => now(),
            'account_exists' => true,
        ]);

        // Qui chiameremo il TikTokCreatorInfoService per completare il profilo
        // Per la FASE 1, è sufficiente memorizzare i token.
        $creatorInfoService = new TikTokCreatorInfoService;
        $creatorInfoService->updateCreatorInfo($account);

        return redirect()->route('clients.show', $account->client_id)
            ->with('success', 'Account TikTok collegato con successo e capabilities aggiornate.');
    }
}
