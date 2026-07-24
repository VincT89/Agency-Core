<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Client;
use App\Models\MarketingCampaign;
use App\Models\ClientSocialAccount;
use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class E2EMockSeeder extends Seeder
{
    public function run()
    {
        $user = User::firstOrCreate(
            ['email' => 'marketing@agency.local'],
            [
                'name' => 'Marketing User',
                'password' => Hash::make('password'),
                'role' => UserRole::Marketing->value,
            ]
        );

        $client = Client::firstOrCreate(
            ['email' => 'client@client.local'],
            [
                'name' => 'Test Client E2E',
                'slug' => 'test-client-e2e',
                'phone' => '123456789',
            ]
        );

        $campaign = MarketingCampaign::firstOrCreate(
            ['name' => 'E2E Campaign'],
            [
                'client_id' => $client->id,
                'description' => 'Test campaign for E2E flow',
            ]
        );

        // Meta (Facebook/Instagram)
        ClientSocialAccount::updateOrCreate(
            ['client_id' => $client->id, 'platform' => 'facebook'],
            [
                'provider_account_id' => 'fb_fake_123',
                'access_token' => 'fake_fb_token_123',
                'refresh_token' => null,
                'token_expires_at' => now()->addDays(60),
                'account_name' => 'Test FB Page',
                'instagram_business_account_id' => 'ig_fake_123',
                'username' => 'test_ig_account',
                'api_status' => 'connected'
            ]
        );
        
        // TikTok
        ClientSocialAccount::updateOrCreate(
            ['client_id' => $client->id, 'platform' => 'tiktok'],
            [
                'provider_account_id' => 'tk_fake_123',
                'access_token' => 'fake_tk_token_123',
                'refresh_token' => 'fake_tk_refresh_123',
                'token_expires_at' => now()->addDays(60),
                'account_name' => 'Test TikTok Account',
                'api_status' => 'connected'
            ]
        );
    }
}
