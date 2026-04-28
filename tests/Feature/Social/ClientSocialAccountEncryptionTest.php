<?php

namespace Tests\Feature\Social;

use Tests\TestCase;
use App\Models\ClientSocialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class ClientSocialAccountEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_tokens_are_encrypted_in_database()
    {
        $client = \App\Models\Client::factory()->create();
        
        $account = ClientSocialAccount::create([
            'client_id' => $client->id,
            'platform' => 'facebook',
            'access_token' => 'super-secret-plain-token',
            'refresh_token' => 'super-secret-refresh-token',
        ]);

        // Get raw data from database
        $rawAccessToken = DB::table('client_social_accounts')->where('id', $account->id)->value('access_token');
        $rawRefreshToken = DB::table('client_social_accounts')->where('id', $account->id)->value('refresh_token');

        // Assert the raw database value is NOT the plain text (it should be an encrypted string payload)
        $this->assertNotSame('super-secret-plain-token', $rawAccessToken);
        $this->assertNotSame('super-secret-refresh-token', $rawRefreshToken);

        // Assert that Eloquent decrypts it correctly
        $this->assertSame('super-secret-plain-token', $account->fresh()->access_token);
        $this->assertSame('super-secret-refresh-token', $account->fresh()->refresh_token);
    }
}
