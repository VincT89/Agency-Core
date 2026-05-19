<?php

namespace App\Domain\Social\Actions;

use App\Models\Client;
use App\Models\ClientSocialAccount;

class CreateOrUpdateClientSocialAccountAction
{
    public function execute(Client $client, string $platform, array $data): ClientSocialAccount
    {
        $account = ClientSocialAccount::where('client_id', $client->id)
            ->where('platform', $platform)
            ->first();
        $isOauth = (isset($data['connection_mode']) && $data['connection_mode'] === 'oauth') 
            || ($account && $account->connection_mode === \App\Enums\Social\SocialConnectionMode::Oauth);
        
        $updateData = [
            'account_name' => blank($data['account_name'] ?? null) ? null : $data['account_name'],
            'account_url' => blank($data['account_url'] ?? null) ? null : $data['account_url'],
            'username' => blank($data['username'] ?? null) ? null : $data['username'],
            'account_exists' => (bool) ($data['account_exists'] ?? false),
            
            'notes' => blank($data['notes'] ?? null) ? null : $data['notes'],
            'api_notes' => blank($data['api_notes'] ?? null) ? null : $data['api_notes'],
        ];

        if (isset($data['connection_mode'])) {
            $updateData['connection_mode'] = $data['connection_mode'];
        }
        
        if (!$isOauth) {
            $updateData['access_method'] = blank($data['access_method'] ?? null) ? 'unknown' : $data['access_method'];
            $updateData['access_status'] = blank($data['access_status'] ?? null) ? 'not_started' : $data['access_status'];
            $updateData['is_ready_to_publish'] = (bool) ($data['is_ready_to_publish'] ?? false);
            
            $updateData['business_manager_id'] = blank($data['business_manager_id'] ?? null) ? null : $data['business_manager_id'];
            $updateData['business_center_id'] = blank($data['business_center_id'] ?? null) ? null : $data['business_center_id'];
            $updateData['tiktok_account_id'] = blank($data['tiktok_account_id'] ?? null) ? null : $data['tiktok_account_id'];
            $updateData['credential_location'] = blank($data['credential_location'] ?? null) ? null : $data['credential_location'];
        } else {
            $updateData['access_method'] = 'unknown';
            $updateData['access_status'] = 'not_started';
            $updateData['is_ready_to_publish'] = false;
            
            $updateData['business_manager_id'] = null;
            $updateData['business_center_id'] = null;
            $updateData['tiktok_account_id'] = null;
            $updateData['credential_location'] = null;
        }
        
        if (isset($data['api_provider'])) {
            $updateData['api_provider'] = blank($data['api_provider'] ?? null) ? null : $data['api_provider'];
        }
        if (isset($data['api_status'])) {
            $updateData['api_status'] = blank($data['api_status'] ?? null) ? 'not_configured' : $data['api_status'];
        }

        if (isset($data['connection_strategy'])) {
            $updateData['connection_strategy'] = $data['connection_strategy'];
        }

        if (array_key_exists('agency_social_asset_id', $data)) {
            $newAssetId = blank($data['agency_social_asset_id'] ?? null) ? null : $data['agency_social_asset_id'];
            
            $oldAssetId = $account ? $account->agency_social_asset_id : null;
            
            // Se l'asset cambia (o è la prima assegnazione), aggiorniamo lo stato
            if ($oldAssetId != $newAssetId) {
                $updateData['assignment_changed_by'] = auth()->id();
                $updateData['assignment_changed_at'] = now();
                
                if ($newAssetId === null) {
                    $updateData['api_status'] = \App\Enums\Social\SocialApiStatus::NotConfigured;
                    $updateData['connected_at'] = null;
                    $updateData['is_ready_to_publish'] = false;
                } else {
                    $updateData['api_status'] = \App\Enums\Social\SocialApiStatus::Connected;
                    $updateData['connected_at'] = now(); // per compatibilità visiva
                }
            }
            
            $updateData['agency_social_asset_id'] = $newAssetId;
        }

        return ClientSocialAccount::updateOrCreate(
            [
                'client_id' => $client->id,
                'platform' => $platform,
            ],
            $updateData
        );
    }
}
