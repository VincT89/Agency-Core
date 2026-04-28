<?php

namespace App\Domain\Social\Actions;

use App\Models\Client;
use App\Models\ClientSocialAccount;

class CreateOrUpdateClientSocialAccountAction
{
    public function execute(Client $client, string $platform, array $data): ClientSocialAccount
    {
        return ClientSocialAccount::updateOrCreate(
            [
                'client_id' => $client->id,
                'platform' => $platform,
            ],
            [
                'account_name' => $data['account_name'] ?? null,
                'account_url' => $data['account_url'] ?? null,
                'username' => $data['username'] ?? null,
                
                'account_exists' => $data['account_exists'] ?? 'unknown',
                'access_method' => $data['access_method'] ?? 'unknown',
                'access_status' => $data['access_status'] ?? 'not_started',
                'is_ready_to_publish' => $data['is_ready_to_publish'] ?? false,
                
                'business_manager_id' => $data['business_manager_id'] ?? null,
                'business_center_id' => $data['business_center_id'] ?? null,
                'instagram_business_account_id' => $data['instagram_business_account_id'] ?? null,
                'tiktok_account_id' => $data['tiktok_account_id'] ?? null,
                
                'credential_location' => $data['credential_location'] ?? null,
                
                'api_provider' => $data['api_provider'] ?? null,
                'api_status' => $data['api_status'] ?? 'not_configured',
                
                'notes' => $data['notes'] ?? null,
                'api_notes' => $data['api_notes'] ?? null,
                
                // Legacy support maps
                'facebook_page_url' => ($platform === 'facebook') ? ($data['account_url'] ?? null) : null,
                'instagram_profile_url' => ($platform === 'instagram') ? ($data['account_url'] ?? null) : null,
                'meta_business_manager_id' => $data['business_manager_id'] ?? null,
                'facebook_page_id' => $data['page_id'] ?? null,
            ]
        );
    }
}
