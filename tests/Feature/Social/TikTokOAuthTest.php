<?php

namespace Tests\Feature\Social;

use Tests\TestCase;
use App\Models\ClientSocialAccount;
use App\Models\User;
use App\Enums\Social\SocialPlatform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class TikTokOAuthTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        // Presupponiamo un utente admin autenticato per accedere alle rotte admin.
        $this->admin = User::factory()->create(['role' => 'admin'] ?? []); 
        $this->actingAs($this->admin);

        // Mock rotte admin per evitare RouteNotFoundException nel controller
        \Illuminate\Support\Facades\Route::get('/admin/clients', fn() => 'index')->name('admin.clients.index');
        \Illuminate\Support\Facades\Route::get('/admin/clients/{client}', fn() => 'show')->name('admin.clients.show');
        app('router')->getRoutes()->refreshNameLookups();
    }

    public function test_redirect_fails_if_session_state_missing()
    {
        // Nessuna sessione configurata
        $response = $this->get(route('admin.social.tiktok.redirect'));

        $response->assertRedirect(route('admin.clients.index'));
        $response->assertSessionHas('error');
    }

    public function test_redirect_fails_on_cross_tenant_mismatch()
    {
        $account = ClientSocialAccount::factory()->create([
            'platform' => SocialPlatform::Tiktok,
        ]);

        $this->withSession([
            'tiktok_oauth_account_id' => $account->id,
            'tiktok_oauth_client_id' => 999, // ID errato
            'tiktok_oauth_expected_platform' => SocialPlatform::Tiktok->value,
        ]);

        $response = $this->get(route('admin.social.tiktok.redirect'));

        $response->assertRedirect(route('admin.clients.show', $account->client_id));
        $response->assertSessionHas('error');
    }

    public function test_callback_fails_with_invalid_state()
    {
        $account = ClientSocialAccount::factory()->create([
            'platform' => SocialPlatform::Tiktok,
        ]);

        $this->withSession([
            'tiktok_oauth_account_id' => $account->id,
            'tiktok_oauth_client_id' => $account->client_id,
            'tiktok_oauth_expected_platform' => SocialPlatform::Tiktok->value,
            'tiktok_oauth_state' => 'valid_state_123',
        ]);

        $response = $this->get(route('admin.social.tiktok.callback', [
            'state' => 'wrong_state_456',
            'code' => 'dummy_code',
        ]));

        $response->assertRedirect(route('admin.clients.show', $account->client_id));
        $response->assertSessionHas('error', 'Stato OAuth non valido o scaduto. Riprova.');
    }

    public function test_callback_success_updates_account()
    {
        $account = ClientSocialAccount::factory()->create([
            'platform' => SocialPlatform::Tiktok,
        ]);

        $this->withSession([
            'tiktok_oauth_account_id' => $account->id,
            'tiktok_oauth_client_id' => $account->client_id,
            'tiktok_oauth_expected_platform' => SocialPlatform::Tiktok->value,
            'tiktok_oauth_state' => 'valid_state_123',
        ]);

        Http::fake([
            'open.tiktokapis.com/v2/oauth/token/*' => Http::response([
                'access_token' => 'new_access_token',
                'refresh_token' => 'new_refresh_token',
                'expires_in' => 86400,
                'open_id' => 'dummy_open_id',
                'scope' => 'user.info.basic,video.upload',
            ], 200),
            // Fake per CreatorInfoService se viene chiamato
            'open.tiktokapis.com/v2/post/publish/creator_info/query/*' => Http::response([
                'data' => [
                    'creator_avatar_url' => 'https://example.com/avatar.jpg',
                    'creator_nickname' => 'TestUser',
                ]
            ], 200)
        ]);

        $response = $this->get(route('admin.social.tiktok.callback', [
            'state' => 'valid_state_123',
            'code' => 'dummy_code',
        ]));

        $response->assertRedirect(route('admin.clients.show', $account->client_id));
        $response->assertSessionHas('success');

        $account->refresh();
        $this->assertEquals('new_access_token', $account->access_token);
        $this->assertEquals(\App\Enums\Social\SocialApiStatus::Connected, $account->api_status);
        $this->assertContains('video.upload', $account->scopes ?? []);
    }
}
