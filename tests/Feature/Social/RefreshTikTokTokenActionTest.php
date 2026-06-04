<?php

namespace Tests\Feature\Social;

use Tests\TestCase;
use App\Models\ClientSocialAccount;
use App\Enums\Social\SocialPlatform;
use App\Enums\Social\SocialApiStatus;
use App\Domain\Social\Actions\RefreshTikTokTokenAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class RefreshTikTokTokenActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_does_not_delete_token_on_refresh_failure()
    {
        $account = ClientSocialAccount::factory()->create([
            'platform' => SocialPlatform::Tiktok,
            'access_token' => 'old_access_token',
            'refresh_token' => 'old_refresh_token',
            'api_status' => SocialApiStatus::Connected,
        ]);

        Http::fake([
            'open.tiktokapis.com/v2/oauth/token/' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $action = new RefreshTikTokTokenAction();
        $result = $action->execute($account);

        $this->assertFalse($result);

        $account->refresh();
        // Verifica che il token non sia stato svuotato, ma lo stato sia andato in errore
        $this->assertEquals('old_access_token', $account->access_token);
        $this->assertEquals('old_refresh_token', $account->refresh_token);
        $this->assertEquals(SocialApiStatus::Error, $account->api_status);
        $this->assertStringContainsString('Refresh token fallito o scaduto', $account->api_notes);
    }
}
