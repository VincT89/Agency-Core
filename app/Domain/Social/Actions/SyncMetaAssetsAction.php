<?php

namespace App\Domain\Social\Actions;

use App\Domain\Social\DTO\SyncMetaAssetsResult;
use App\Enums\Social\AgencyConnectionStatus;
use App\Enums\Social\PublishingStatus;
use App\Enums\Social\SocialAssetType;
use App\Models\AgencySocialAsset;
use App\Models\AgencySocialConnection;
use App\Support\Http\SocialProviderHttp;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncMetaAssetsAction
{
    /**
     * Sincronizza Pagine Facebook e account IG Business associati per una data connessione
     */
    public function execute(AgencySocialConnection $connection): SyncMetaAssetsResult
    {
        if (! $connection->access_token) {
            return new SyncMetaAssetsResult(errors: 1, errorMessage: 'Access token assente');
        }

        $totalFound = 0;
        $newCreated = 0;
        $updated = 0;
        $revoked = 0;
        $errors = 0;

        $apiVersion = config('services.meta.graph_version', 'v19.0');
        $endpoint = "https://graph.facebook.com/{$apiVersion}/me/accounts";

        try {
            $pages = [];
            $nextUrl = $endpoint;
            $queryParams = [
                'access_token' => $connection->access_token,
                'fields' => 'id,name,access_token,instagram_business_account{id,username,profile_picture_url},tasks,picture',
                'limit' => 100,
            ];
            $visitedUrls = [];
            $pageCount = 0;
            $maxPages = max(1, (int) config('services.meta.max_sync_pages', 25));

            while ($nextUrl) {
                $pageCount++;

                if ($pageCount > $maxPages) {
                    throw new \RuntimeException('La paginazione Meta ha superato il limite di sicurezza.');
                }

                if (! $this->isTrustedGraphUrl($nextUrl)) {
                    throw new \RuntimeException('Meta ha restituito un URL di paginazione non attendibile.');
                }

                $pageFingerprint = hash('sha256', $nextUrl);
                if (isset($visitedUrls[$pageFingerprint])) {
                    throw new \RuntimeException('Meta ha restituito un ciclo nella paginazione.');
                }
                $visitedUrls[$pageFingerprint] = true;

                $response = SocialProviderHttp::meta(retrySafe: true)
                    ->get($nextUrl, $queryParams);

                if ($response->failed()) {
                    $safeError = $this->safeApiError($response);
                    $connection->update([
                        'status' => AgencyConnectionStatus::SyncFailed,
                        'last_api_error' => $safeError,
                        'last_api_check_at' => now(),
                    ]);

                    return new SyncMetaAssetsResult(errors: 1, errorMessage: $safeError);
                }

                $data = $response->json();
                $pages = array_merge($pages, $data['data'] ?? []);

                // Controlla se c'è una pagina successiva
                $nextUrl = $data['paging']['next'] ?? null;
                if ($nextUrl !== null && ! is_string($nextUrl)) {
                    throw new \RuntimeException('Meta ha restituito una paginazione non valida.');
                }

                // Svuota queryParams per le chiamate successive, dato che la nextUrl contiene già tutti i parametri
                if ($nextUrl) {
                    $queryParams = [];
                }
            }

            $totalFound = count($pages);

            $syncedAssetIds = [];

            foreach ($pages as $page) {
                if (! is_array($page) || empty($page['id']) || empty($page['name'])) {
                    throw new \RuntimeException('Meta ha restituito un asset privo dei campi obbligatori.');
                }

                $tasks = $page['tasks'] ?? [];
                $canPublish = count(array_intersect($tasks, ['CREATE_CONTENT', 'MANAGE'])) > 0;
                $fbPublishingStatus = $canPublish ? PublishingStatus::Ready : PublishingStatus::MissingPermissions;
                $pageAccessToken = $page['access_token'] ?? null;

                // Sincronizza Pagina Facebook (Root Asset)
                $fbAsset = AgencySocialAsset::updateOrCreate(
                    [
                        'agency_social_connection_id' => $connection->id,
                        'provider' => 'facebook',
                        'provider_asset_id' => $page['id'],
                    ],
                    [
                        'platform' => 'facebook',
                        'asset_type' => SocialAssetType::FacebookPage,
                        'name' => $page['name'],
                        'facebook_page_id' => $page['id'],
                        'page_access_token' => $pageAccessToken,
                        'page_token_status' => filled($pageAccessToken) ? 'connected' : 'invalid',
                        'page_token_last_validated_at' => now(),
                        'capabilities' => $tasks,
                        'raw_payload' => $this->withoutSecrets($page),
                        'status' => AgencyConnectionStatus::Connected,
                        'publishing_status' => $fbPublishingStatus,
                        'is_active' => true,
                        'revoked_at' => null,
                        'last_synced_at' => now(),
                    ]
                );

                if ($fbAsset->wasRecentlyCreated) {
                    $newCreated++;
                } else {
                    $updated++;
                }
                $syncedAssetIds[] = $fbAsset->id;

                // Se c'è un account IG associato, sincronizzalo
                if (isset($page['instagram_business_account'])) {
                    $igData = $page['instagram_business_account'];
                    $totalFound++;

                    $igAsset = AgencySocialAsset::updateOrCreate(
                        [
                            'agency_social_connection_id' => $connection->id,
                            'provider' => 'facebook',
                            'provider_asset_id' => $igData['id'], // L'ID dell'account IG Business
                        ],
                        [
                            'platform' => 'instagram',
                            'asset_type' => SocialAssetType::InstagramBusinessAccount,
                            'parent_asset_id' => $fbAsset->id, // [CRITICAL] Nidificazione per ereditare il token
                            'name' => $igData['username'] ?? 'Account IG',
                            'username' => $igData['username'] ?? null,
                            'instagram_business_account_id' => $igData['id'],
                            'page_access_token' => null, // Non duplicare il token!
                            'raw_payload' => $igData,
                            'status' => AgencyConnectionStatus::Connected,
                            'publishing_status' => $fbPublishingStatus,
                            'is_active' => true,
                            'revoked_at' => null,
                            'last_synced_at' => now(),
                        ]
                    );

                    if ($igAsset->wasRecentlyCreated) {
                        $newCreated++;
                    } else {
                        $updated++;
                    }
                    $syncedAssetIds[] = $igAsset->id;
                }
            }

            // Marcare come inattivi (soft delete logico) gli asset che avevamo prima ma che non ci sono più arrivati
            $revokedAssets = AgencySocialAsset::where('agency_social_connection_id', $connection->id)
                ->whereNotIn('id', $syncedAssetIds)
                ->where('is_active', true)
                ->get();

            foreach ($revokedAssets as $rAsset) {
                $rAsset->update([
                    'is_active' => false,
                    'revoked_at' => now(),
                    'status' => AgencyConnectionStatus::Revoked,
                    'publishing_status' => PublishingStatus::MissingPermissions,
                ]);
                $revoked++;
            }

            $connection->update([
                'status' => AgencyConnectionStatus::Connected,
                'last_sync_at' => now(),
                'last_api_check_at' => now(),
                'last_api_error' => null,
            ]);

            return new SyncMetaAssetsResult(
                totalFound: $totalFound,
                newCreated: $newCreated,
                updated: $updated,
                revoked: $revoked,
                missingPermissions: 0,
                errors: 0
            );

        } catch (\Throwable $e) {
            $safeError = $this->safeExceptionMessage($e->getMessage());
            Log::error('SyncMetaAssetsAction Exception', ['error' => $safeError]);
            $connection->update([
                'status' => AgencyConnectionStatus::SyncFailed,
                'last_api_error' => $safeError,
                'last_api_check_at' => now(),
            ]);

            return new SyncMetaAssetsResult(errors: 1, errorMessage: $safeError);
        }
    }

    private function isTrustedGraphUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts)) {
            return false;
        }

        return strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && strtolower((string) ($parts['host'] ?? '')) === 'graph.facebook.com'
            && ! isset($parts['user'], $parts['pass'])
            && (! isset($parts['port']) || (int) $parts['port'] === 443);
    }

    private function withoutSecrets(array $payload): array
    {
        $sensitiveKeys = [
            'access_token',
            'page_access_token',
            'client_secret',
            'app_secret',
        ];

        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), $sensitiveKeys, true)) {
                unset($payload[$key]);

                continue;
            }

            if (is_array($value)) {
                $payload[$key] = $this->withoutSecrets($value);
            }
        }

        return $payload;
    }

    private function safeApiError($response): string
    {
        $providerMessage = data_get($response->json(), 'error.message');
        $summary = is_string($providerMessage) && $providerMessage !== ''
            ? $providerMessage
            : 'Risposta di errore senza dettagli utilizzabili.';

        return $this->safeExceptionMessage(
            Str::limit("Errore API Meta (HTTP {$response->status()}): {$summary}", 1000, '')
        );
    }

    private function safeExceptionMessage(string $message): string
    {
        $redacted = preg_replace(
            '/([?&](?:access_token|client_secret|app_secret)=)[^&\s]+/i',
            '$1[REDACTED]',
            $message
        ) ?? 'Errore durante la sincronizzazione Meta.';

        return Str::limit($redacted, 1000, '');
    }
}
