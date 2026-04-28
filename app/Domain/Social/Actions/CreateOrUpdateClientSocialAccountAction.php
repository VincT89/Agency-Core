<?php

namespace App\Domain\Social\Actions;

use App\Models\Client;
use App\Models\ClientSocialAccount;
use App\Enums\Social\ClientSocialAccessStatus;
use App\Enums\Social\MetaApiStatus;

class CreateOrUpdateClientSocialAccountAction
{
    public function execute(Client $client, array $data): ClientSocialAccount
    {
        $provider = $data['provider'] ?? 'meta';

        // Extract enums if they are passed as enum instances or raw strings
        $accessStatus = isset($data['access_status']) 
            ? ($data['access_status'] instanceof ClientSocialAccessStatus ? $data['access_status']->value : $data['access_status'])
            : ClientSocialAccessStatus::Missing->value;
            
        $apiStatus = isset($data['api_status'])
            ? ($data['api_status'] instanceof MetaApiStatus ? $data['api_status']->value : $data['api_status'])
            : MetaApiStatus::NotConfigured->value;

        return ClientSocialAccount::updateOrCreate(
            [
                'client_id' => $client->id,
                'provider' => $provider,
            ],
            [
                'facebook_page_url' => $data['facebook_page_url'] ?? null,
                'instagram_profile_url' => $data['instagram_profile_url'] ?? null,
                'meta_business_manager_id' => $data['meta_business_manager_id'] ?? null,
                'has_agency_access' => $data['has_agency_access'] ?? false,
                'access_status' => $accessStatus,
                'notes' => $data['notes'] ?? null,
                // These might be updated via API later, not usually through the form
                'facebook_page_id' => $data['facebook_page_id'] ?? null,
                'instagram_business_account_id' => $data['instagram_business_account_id'] ?? null,
                'api_status' => $apiStatus,
            ]
        );
    }
}
