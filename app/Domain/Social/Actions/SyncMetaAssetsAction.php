<?php

namespace App\Domain\Social\Actions;

use App\Models\AgencySocialConnection;
use App\Models\AgencySocialAsset;
use App\Domain\Social\DTO\SyncMetaAssetsResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Enums\Social\AgencyConnectionStatus;
use App\Enums\Social\SocialAssetType;
use App\Enums\Social\PublishingStatus;

class SyncMetaAssetsAction
{
    /**
     * Sincronizza Pagine Facebook e account IG Business associati per una data connessione
     */
    public function execute(AgencySocialConnection $connection): SyncMetaAssetsResult
    {
        if (!$connection->access_token) {
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
                'limit' => 100
            ];

            while ($nextUrl) {
                $response = Http::get($nextUrl, $queryParams);

                if ($response->failed()) {
                    $connection->update([
                        'status' => AgencyConnectionStatus::SyncFailed,
                        'last_api_error' => $response->body(),
                        'last_api_check_at' => now(),
                    ]);
                    return new SyncMetaAssetsResult(errors: 1, errorMessage: 'Errore API Meta: ' . $response->body());
                }

                $data = $response->json();
                $pages = array_merge($pages, $data['data'] ?? []);

                // Controlla se c'è una pagina successiva
                $nextUrl = $data['paging']['next'] ?? null;
                // Svuota queryParams per le chiamate successive, dato che la nextUrl contiene già tutti i parametri
                if ($nextUrl) {
                    $queryParams = []; 
                }
            }

            $totalFound = count($pages);

            $syncedAssetIds = [];

            foreach ($pages as $page) {
                $tasks = $page['tasks'] ?? [];
                $canPublish = count(array_intersect($tasks, ['CREATE_CONTENT', 'MANAGE'])) > 0;
                $fbPublishingStatus = $canPublish ? PublishingStatus::Ready : PublishingStatus::MissingPermissions;

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
                        'page_access_token' => $page['access_token'] ?? null,
                        'page_token_status' => $page['access_token'] ? 'connected' : 'invalid',
                        'page_token_last_validated_at' => now(),
                        'capabilities' => $tasks,
                        'raw_payload' => $page,
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

        } catch (\Exception $e) {
            Log::error('SyncMetaAssetsAction Exception', ['error' => $e->getMessage()]);
            $connection->update([
                'status' => AgencyConnectionStatus::SyncFailed,
                'last_api_error' => $e->getMessage(),
                'last_api_check_at' => now(),
            ]);
            return new SyncMetaAssetsResult(errors: 1, errorMessage: $e->getMessage());
        }
    }
}
