<?php

namespace Tests\Unit\Social;

use App\Domain\Social\TikTok\TikTokContentPostingService;
use App\Exceptions\Social\TikTokApiException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TikTokContentPostingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.tiktok.api_base' => 'https://open.tiktokapis.com',
            'services.tiktok.creator_info_ttl_seconds' => 300,
        ]);
        Cache::flush();
    }

    public function test_creator_info_uses_documented_headers_and_empty_body(): void
    {
        Http::fake([
            'open.tiktokapis.com/v2/post/publish/creator_info/query/' => Http::response([
                'data' => [
                    'creator_nickname' => 'Creator di prova',
                    'privacy_level_options' => ['SELF_ONLY'],
                ],
                'error' => [
                    'code' => 'ok',
                    'message' => '',
                    'log_id' => 'success-reference',
                ],
            ], 200),
        ]);

        $result = app(TikTokContentPostingService::class)->queryCreatorInfo(
            'dummy-access-token',
            'creator-info-success',
            true
        );

        $this->assertSame('Creator di prova', $result['creator_nickname']);
        $this->assertSame(['SELF_ONLY'], $result['privacy_level_options']);
        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://open.tiktokapis.com/v2/post/publish/creator_info/query/'
                && $request->hasHeader('Authorization', 'Bearer dummy-access-token')
                && $request->hasHeader('Content-Type', 'application/json; charset=UTF-8')
                && $request->body() === '';
        });
    }

    public function test_creator_info_rejects_tiktok_error_returned_with_http_200(): void
    {
        Http::fake([
            'open.tiktokapis.com/v2/post/publish/creator_info/query/' => Http::response([
                'data' => [],
                'error' => [
                    'code' => 'reached_active_user_cap',
                    'message' => 'Sandbox active user limit reached',
                    'log_id' => 'failure-reference-123',
                ],
            ], 200),
        ]);

        try {
            app(TikTokContentPostingService::class)->queryCreatorInfo(
                'dummy-access-token',
                'creator-info-failure',
                true
            );
            $this->fail('Era attesa una TikTokApiException.');
        } catch (TikTokApiException $exception) {
            $this->assertSame(200, $exception->httpStatus);
            $this->assertSame('failure-reference-123', $exception->requestId);
            $this->assertStringContainsString(
                'Codice TikTok: reached_active_user_cap.',
                $exception->getMessage()
            );
            $this->assertStringContainsString(
                'Riferimento TikTok: failure-reference-123.',
                $exception->getMessage()
            );
            $this->assertStringNotContainsString(
                'dummy-access-token',
                $exception->getMessage()
            );
        }
    }

    public function test_creator_info_surfaces_scope_error_without_exposing_token(): void
    {
        Http::fake([
            'open.tiktokapis.com/v2/post/publish/creator_info/query/' => Http::response([
                'error' => [
                    'code' => 'scope_not_authorized',
                    'message' => 'Denied access_token=provider-secret',
                    'log_id' => 'scope-reference-456',
                ],
            ], 401),
        ]);

        try {
            app(TikTokContentPostingService::class)->queryCreatorInfo(
                'dummy-access-token',
                'creator-info-scope-error',
                true
            );
            $this->fail('Era attesa una TikTokApiException.');
        } catch (TikTokApiException $exception) {
            $this->assertSame(401, $exception->httpStatus);
            $this->assertSame('scope-reference-456', $exception->requestId);
            $this->assertStringContainsString(
                'Codice TikTok: scope_not_authorized.',
                $exception->getMessage()
            );
            $this->assertStringNotContainsString(
                'provider-secret',
                $exception->getMessage()
            );
            $this->assertStringNotContainsString(
                'dummy-access-token',
                $exception->getMessage()
            );
        }
    }
}
