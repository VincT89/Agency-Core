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
                'account_name' => blank($data['account_name'] ?? null) ? null : $data['account_name'],
                'account_url' => blank($data['account_url'] ?? null) ? null : $data['account_url'],
                'username' => blank($data['username'] ?? null) ? null : $data['username'],
                
                'account_exists' => (bool) ($data['account_exists'] ?? false),
                'access_method' => blank($data['access_method'] ?? null) ? 'unknown' : $data['access_method'],
                'access_status' => blank($data['access_status'] ?? null) ? 'not_started' : $data['access_status'],
                'is_ready_to_publish' => (bool) ($data['is_ready_to_publish'] ?? false),
                
                'business_manager_id' => blank($data['business_manager_id'] ?? null) ? null : $data['business_manager_id'],
                'business_center_id' => blank($data['business_center_id'] ?? null) ? null : $data['business_center_id'],
                'instagram_business_account_id' => blank($data['instagram_business_account_id'] ?? null) ? null : $data['instagram_business_account_id'],
                'tiktok_account_id' => blank($data['tiktok_account_id'] ?? null) ? null : $data['tiktok_account_id'],
                
                'credential_location' => blank($data['credential_location'] ?? null) ? null : $data['credential_location'],
                
                'api_provider' => blank($data['api_provider'] ?? null) ? null : $data['api_provider'],
                'api_status' => blank($data['api_status'] ?? null) ? 'not_configured' : $data['api_status'],
                
                'notes' => blank($data['notes'] ?? null) ? null : $data['notes'],
                'api_notes' => blank($data['api_notes'] ?? null) ? null : $data['api_notes'],
                
                // Mappatura campi legacy per compatibilità pre-refactoring
                'facebook_page_url' => ($platform === 'facebook') ? (blank($data['account_url'] ?? null) ? null : $data['account_url']) : null,
                'instagram_profile_url' => ($platform === 'instagram') ? (blank($data['account_url'] ?? null) ? null : $data['account_url']) : null,
                'meta_business_manager_id' => blank($data['business_manager_id'] ?? null) ? null : $data['business_manager_id'],
                'facebook_page_id' => blank($data['page_id'] ?? null) ? null : $data['page_id'],
            ]
        );
    }
}
