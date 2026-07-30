<?php

namespace Tests\Feature\Social;

use App\Enums\Social\SocialApiStatus;
use App\Enums\Social\SocialConnectionMode;
use App\Enums\Social\SocialPlatform;
use App\Jobs\Social\CheckSocialAccountStatusJob;
use App\Models\ClientSocialAccount;
use App\Models\SystemCommandRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocialProviderMaintenanceHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_meta_status_check_uses_bearer_auth_and_configured_version(): void
    {
        config(['services.meta.graph_version' => 'v99.0']);
        $account = ClientSocialAccount::factory()->create([
            'platform' => SocialPlatform::Facebook,
            'api_status' => SocialApiStatus::Error,
            'access_token' => 'meta-secret-token',
        ]);
        Http::fake([
            '*' => Http::response(['id' => 'provider-account'], 200),
        ]);

        (new CheckSocialAccountStatusJob($account->id))->handle();

        $this->assertSame(
            SocialApiStatus::Connected,
            $account->refresh()->api_status
        );
        $this->assertNull($account->last_api_error);
        Http::assertSent(function (Request $request): bool {
            return str_starts_with(
                $request->url(),
                'https://graph.facebook.com/v99.0/me'
            )
                && $request->hasHeader(
                    'Authorization',
                    'Bearer meta-secret-token'
                )
                && ! str_contains($request->url(), 'access_token');
        });
    }

    public function test_permanent_meta_error_is_not_retried_and_is_sanitized(): void
    {
        $account = ClientSocialAccount::factory()->create([
            'platform' => SocialPlatform::Instagram,
            'api_status' => SocialApiStatus::Connected,
            'access_token' => 'meta-secret-token',
        ]);
        Http::fake([
            '*' => Http::response([
                'error' => [
                    'code' => 100,
                    'message' => 'Denied access_token=provider-secret',
                ],
            ], 400),
        ]);

        (new CheckSocialAccountStatusJob($account->id))->handle();

        $account->refresh();
        $this->assertSame(SocialApiStatus::Error, $account->api_status);
        $this->assertStringNotContainsString(
            'provider-secret',
            $account->last_api_error
        );
        $this->assertStringContainsString(
            '[REDACTED]',
            $account->last_api_error
        );
        Http::assertSentCount(1);
    }

    public function test_token_extension_updates_token_without_exposing_it(): void
    {
        $this->configureMeta();
        $account = $this->expiringMetaAccount();
        Http::fake([
            '*' => Http::response([
                'access_token' => 'new-long-lived-token',
                'expires_in' => 5_184_000,
            ], 200),
        ]);

        $this->artisan('social:extend-tokens')->assertSuccessful();

        $account->refresh();
        $this->assertSame('new-long-lived-token', $account->access_token);
        $this->assertTrue($account->token_expires_at->isAfter(now()->addDays(59)));
        $this->assertNull($account->last_api_error);
        Http::assertSentCount(1);
    }

    public function test_token_extension_failure_is_reported_and_sanitized(): void
    {
        $this->configureMeta();
        $account = $this->expiringMetaAccount();
        Http::fake([
            '*' => Http::response([
                'error' => [
                    'code' => 100,
                    'message' => 'Denied access_token=provider-secret',
                ],
            ], 400),
        ]);

        $this->artisan('social:extend-tokens')->assertFailed();

        $account->refresh();
        $this->assertStringNotContainsString(
            'provider-secret',
            $account->last_api_error
        );
        $this->assertStringContainsString(
            '[REDACTED]',
            $account->last_api_error
        );
        $this->assertSame(
            'failed',
            SystemCommandRun::query()->latest('id')->value('status')
        );
        Http::assertSentCount(1);
    }

    private function configureMeta(): void
    {
        config([
            'services.meta.client_id' => 'meta-client-id',
            'services.meta.client_secret' => 'meta-client-secret',
            'services.meta.graph_version' => 'v99.0',
        ]);
    }

    private function expiringMetaAccount(): ClientSocialAccount
    {
        return ClientSocialAccount::factory()->create([
            'platform' => SocialPlatform::Facebook,
            'connection_mode' => SocialConnectionMode::Oauth,
            'api_status' => SocialApiStatus::Connected,
            'access_token' => 'old-short-lived-token',
            'token_expires_at' => now()->addDay(),
        ]);
    }
}
