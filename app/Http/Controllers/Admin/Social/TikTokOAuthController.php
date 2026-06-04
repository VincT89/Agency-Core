<?php

namespace App\Http\Controllers\Admin\Social;

use App\Http\Controllers\Controller;
use App\Models\ClientSocialAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TikTokOAuthController extends Controller
{
    public function redirect(Request $request)
    {
        $accountId = session('tiktok_oauth_account_id');
        $clientId = session('tiktok_oauth_client_id');
        $expectedPlatform = session('tiktok_oauth_expected_platform');

        if (!$accountId || !$clientId || $expectedPlatform !== \App\Enums\Social\SocialPlatform::Tiktok->value) {
            return redirect()->route('admin.clients.index')->with('error', 'Sessione OAuth non inizializzata o contesto invalido. Riprova dal pannello del cliente.');
        }

        $account = ClientSocialAccount::findOrFail($accountId);

        // Blocco sicurezza tenant/platform
        if ($account->client_id !== $clientId || $account->platform !== \App\Enums\Social\SocialPlatform::Tiktok) {
            return redirect()->route('admin.clients.show', $account->client_id)
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
            // Blocco temporaneo "Sprint 2A/2B" finché il direct publish non è approvato.
            // Solleviamo un'eccezione esplicita o blocchiamo l'auth.
            return redirect()->route('admin.clients.show', $account->client_id)
                ->with('error', 'La modalità direct publish (video.publish) è disabilitata temporaneamente. Impostare delivery_mode=draft.');
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

        if (!$accountId || !$clientId || $expectedPlatform !== \App\Enums\Social\SocialPlatform::Tiktok->value) {
            return redirect()->route('admin.clients.index')->with('error', 'Sessione OAuth scaduta o già utilizzata (possibile doppio click o multi-tab).');
        }

        $account = ClientSocialAccount::findOrFail($accountId);

        // Validazione cross-tab del tenant e platform
        if ($account->client_id !== $clientId || $account->platform !== \App\Enums\Social\SocialPlatform::Tiktok) {
            return redirect()->route('admin.clients.show', $account->client_id)
                ->with('error', 'Incoerenza tenant/platform rilevata nel callback. Connessione annullata.');
        }

        if ($error) {
            Log::warning('TikTok OAuth Error', ['error' => $error, 'description' => $errorDescription]);
            return redirect()->route('admin.clients.show', $account->client_id)
                ->with('error', "Errore durante il collegamento TikTok: {$errorDescription}");
        }

        if (!$state || $state !== $sessionState) {
            return redirect()->route('admin.clients.show', $account->client_id)
                ->with('error', 'Stato OAuth non valido o scaduto. Riprova.');
        }

        if (!$code) {
            return redirect()->route('admin.clients.show', $account->client_id)
                ->with('error', 'Codice di autorizzazione non ricevuto da TikTok.');
        }

        // Scambio del codice per l'access token
        $apiBase = config('services.tiktok.api_base', 'https://open.tiktokapis.com');
        
        $response = Http::asForm()->post("{$apiBase}/v2/oauth/token/", [
            'client_key' => config('services.tiktok.client_key'),
            'client_secret' => config('services.tiktok.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => config('services.tiktok.redirect_uri'),
        ]);

        if ($response->failed()) {
            Log::error('TikTok Token Exchange Error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return redirect()->route('admin.clients.show', $account->client_id)
                ->with('error', 'Impossibile completare lo scambio del token con TikTok.');
        }

        $data = $response->json();

        // Salva i token e le informazioni dell'account
        $account->update([
            'access_token' => $data['access_token'] ?? null,
            'refresh_token' => $data['refresh_token'] ?? null,
            'token_expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : null,
            'tiktok_open_id' => $data['open_id'] ?? null,
            'scopes' => explode(',', $data['scope'] ?? ''),
            'api_status' => 'connected',
            'account_exists' => true,
        ]);

        // Qui chiameremo il TikTokCreatorInfoService per completare il profilo
        // Per la FASE 1, è sufficiente memorizzare i token.
        $creatorInfoService = new \App\Domain\Social\TikTok\TikTokCreatorInfoService();
        $creatorInfoService->updateCreatorInfo($account);

        return redirect()->route('admin.clients.show', $account->client_id)
            ->with('success', 'Account TikTok collegato con successo e capabilities aggiornate.');
    }
}

