<?php

namespace Tests\Feature\Social;

use App\Domain\Social\Actions\ResolveFrozenPublicationTargetAction;
use App\Domain\Social\DTOs\PublicationMediaDeliveryResult;
use App\Domain\Social\Publishing\MetaPublisher;
use App\Domain\Social\Services\MetaSnapshotPreflightRules;
use App\Domain\Social\Services\PublicationMediaDeliveryService;
use App\Domain\Social\Services\TikTokSnapshotPreflightRules;
use App\Domain\Social\TikTok\Strategies\PullFromUrlStrategy;
use App\Domain\Social\TikTok\TikTokContentPostingService;
use App\Enums\Social\PublicationFailureClassification;
use App\Enums\Social\SocialApiStatus;
use App\Enums\Social\SocialConnectionStrategy;
use App\Enums\Social\SocialPlatform;
use App\Exceptions\Social\TikTokApiException;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaignPostPublication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProviderCompletenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_facebook_image_carousel_uploads_unpublished_photos_then_feed(): void
    {
        config(['social.publishing.dry_run' => false]);
        Http::fakeSequence()
            ->push(['id' => 'photo-1'], 200)
            ->push(['id' => 'photo-2'], 200)
            ->push(['id' => 'page_post-1'], 200);

        $delivery = $this->mock(PublicationMediaDeliveryService::class);
        $delivery->shouldReceive('deliver')->once()->andReturn([
            new PublicationMediaDeliveryResult(
                true,
                'https://media.example/one.jpg',
                [],
                'image'
            ),
            new PublicationMediaDeliveryResult(
                true,
                'https://media.example/two.jpg',
                [],
                'image'
            ),
        ]);

        $account = ClientSocialAccount::factory()->create([
            'platform' => SocialPlatform::Facebook,
            'api_status' => SocialApiStatus::Connected,
            'connection_strategy' => SocialConnectionStrategy::PlatformOauth,
            'provider_account_id' => 'page-1',
            'facebook_page_id' => 'page-1',
            'access_token' => 'token',
        ]);
        $publication = MarketingCampaignPostPublication::factory()->create([
            'client_social_account_id' => $account->id,
            'platform' => SocialPlatform::Facebook,
            'payload_snapshot' => [
                'caption' => 'Carousel',
                'hashtags' => ['campaign'],
                'target' => [
                    'external_id' => 'page-1',
                    'publication_type' => 'post',
                    'privacy_options' => [],
                ],
                'media' => [
                    ['media_id' => 1, 'media_type' => 'image'],
                    ['media_id' => 2, 'media_type' => 'image'],
                ],
            ],
        ]);

        $result = app(MetaPublisher::class)->publish(
            $publication,
            $account,
            'corr-carousel'
        );

        $this->assertTrue(
            $result->isSuccess(),
            $result->errorMessage ?? 'Facebook carousel did not complete.'
        );
        $this->assertSame('page_post-1', $result->externalPostId);
        Http::assertSentCount(3);
        Http::assertSent(function (Request $request): bool {
            return str_ends_with($request->url(), '/page-1/photos')
                && $request['published'] === false;
        });
        Http::assertSent(function (Request $request): bool {
            return str_ends_with($request->url(), '/page-1/feed')
                && $request['attached_media'] === [
                    ['media_fbid' => 'photo-1'],
                    ['media_fbid' => 'photo-2'],
                ];
        });
    }

    public function test_facebook_success_response_without_post_id_is_rejected(): void
    {
        config(['social.publishing.dry_run' => false]);
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([], 200),
        ]);
        $account = ClientSocialAccount::factory()->create([
            'platform' => SocialPlatform::Facebook,
            'api_status' => SocialApiStatus::Connected,
            'connection_strategy' => SocialConnectionStrategy::PlatformOauth,
            'provider_account_id' => 'page-1',
            'facebook_page_id' => 'page-1',
            'access_token' => 'token',
        ]);
        $publication = MarketingCampaignPostPublication::factory()->create([
            'client_social_account_id' => $account->id,
            'platform' => SocialPlatform::Facebook,
            'payload_snapshot' => [
                'caption' => 'Text post',
                'hashtags' => [],
                'target' => [
                    'external_id' => 'page-1',
                    'publication_type' => 'post',
                    'privacy_options' => [],
                ],
                'media' => [],
            ],
        ]);

        $result = app(MetaPublisher::class)->publish(
            $publication,
            $account,
            'corr-missing-id'
        );

        $this->assertFalse($result->isSuccess());
        $this->assertSame(
            PublicationFailureClassification::Permanent,
            $result->failureClassification
        );
        $this->assertStringContainsString(
            'ID del contenuto',
            $result->errorMessage
        );
    }

    public function test_instagram_single_video_legacy_type_creates_a_reels_container(): void
    {
        config([
            'services.meta.graph_version' => 'v25.0',
            'social.publishing.dry_run' => false,
        ]);
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'id' => 'ig-video-container-1',
            ], 200),
        ]);

        $delivery = $this->mock(PublicationMediaDeliveryService::class);
        $delivery->shouldReceive('deliver')->once()->andReturn([
            new PublicationMediaDeliveryResult(
                true,
                'https://media.example/video.mp4',
                [],
                'video'
            ),
        ]);

        $account = ClientSocialAccount::factory()->create([
            'platform' => SocialPlatform::Instagram,
            'api_status' => SocialApiStatus::Connected,
            'connection_strategy' => SocialConnectionStrategy::PlatformOauth,
            'provider_account_id' => 'ig-user-1',
            'instagram_business_account_id' => 'ig-user-1',
            'access_token' => 'token',
        ]);
        $publication = MarketingCampaignPostPublication::factory()->create([
            'client_social_account_id' => $account->id,
            'platform' => SocialPlatform::Instagram,
            'payload_snapshot' => [
                'caption' => 'Single video',
                'hashtags' => [],
                'target' => [
                    'external_id' => 'ig-user-1',
                    'publication_type' => 'video',
                    'privacy_options' => [],
                ],
                'media' => [[
                    'media_id' => 1,
                    'media_type' => 'video',
                ]],
            ],
        ]);

        $result = app(MetaPublisher::class)->publish(
            $publication,
            $account,
            'corr-instagram-video'
        );

        $this->assertTrue($result->isProcessing());
        $this->assertSame(
            'ig-video-container-1',
            $result->externalContainerId
        );
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            return str_ends_with($request->url(), '/ig-user-1/media')
                && $request['media_type'] === 'REELS'
                && $request['video_url'] === 'https://media.example/video.mp4';
        });
    }

    public function test_instagram_carousel_video_child_keeps_video_media_type(): void
    {
        config([
            'services.meta.graph_version' => 'v25.0',
            'social.publishing.dry_run' => false,
        ]);
        Http::fakeSequence()
            ->push(['id' => 'ig-image-child-1'], 200)
            ->push(['id' => 'ig-video-child-1'], 200);

        $delivery = $this->mock(PublicationMediaDeliveryService::class);
        $delivery->shouldReceive('deliver')->once()->andReturn([
            new PublicationMediaDeliveryResult(
                true,
                'https://media.example/image.jpg',
                [],
                'image'
            ),
            new PublicationMediaDeliveryResult(
                true,
                'https://media.example/video.mp4',
                [],
                'video'
            ),
        ]);

        $account = ClientSocialAccount::factory()->create([
            'platform' => SocialPlatform::Instagram,
            'api_status' => SocialApiStatus::Connected,
            'connection_strategy' => SocialConnectionStrategy::PlatformOauth,
            'provider_account_id' => 'ig-user-2',
            'instagram_business_account_id' => 'ig-user-2',
            'access_token' => 'token',
        ]);
        $publication = MarketingCampaignPostPublication::factory()->create([
            'client_social_account_id' => $account->id,
            'platform' => SocialPlatform::Instagram,
            'payload_snapshot' => [
                'caption' => 'Mixed carousel',
                'hashtags' => [],
                'target' => [
                    'external_id' => 'ig-user-2',
                    'publication_type' => 'post',
                    'privacy_options' => [],
                ],
                'media' => [
                    [
                        'media_id' => 1,
                        'media_type' => 'image',
                    ],
                    [
                        'media_id' => 2,
                        'media_type' => 'video',
                    ],
                ],
            ],
        ]);

        $result = app(MetaPublisher::class)->publish(
            $publication,
            $account,
            'corr-instagram-carousel'
        );

        $this->assertTrue($result->isProcessing());
        Http::assertSentCount(2);
        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return str_ends_with($request->url(), '/ig-user-2/media')
                && ($data['media_type'] ?? null) === 'VIDEO'
                && ($data['video_url'] ?? null) === 'https://media.example/video.mp4'
                && ($data['is_carousel_item'] ?? null) === 'true';
        });
    }

    public function test_meta_snapshot_preflight_rejects_unsupported_media_formats(): void
    {
        $result = app(MetaSnapshotPreflightRules::class)->validate([
            'platform' => SocialPlatform::Facebook->value,
            'caption' => 'Unsupported attachment',
            'media' => [[
                'media_type' => 'audio',
                'mime_type' => 'audio/mpeg',
            ]],
        ]);

        $this->assertFalse($result->isPass);
        $this->assertContains(
            'Meta media format is not supported.',
            $result->errors
        );
    }

    public function test_meta_snapshot_preflight_allows_instagram_mixed_carousel_formats(): void
    {
        $result = app(MetaSnapshotPreflightRules::class)->validate([
            'platform' => SocialPlatform::Instagram->value,
            'caption' => 'Mixed carousel',
            'target' => ['publication_type' => 'post'],
            'media' => [
                [
                    'media_type' => 'image',
                    'mime_type' => 'image/jpeg',
                ],
                [
                    'media_type' => 'video',
                    'mime_type' => 'video/mp4',
                ],
            ],
        ]);

        $this->assertTrue(
            $result->isPass,
            implode(', ', $result->errors)
        );
    }

    public function test_meta_snapshot_preflight_rejects_instagram_webm_video(): void
    {
        $result = app(MetaSnapshotPreflightRules::class)->validate([
            'platform' => SocialPlatform::Instagram->value,
            'caption' => 'Unsupported Instagram video',
            'target' => ['publication_type' => 'post'],
            'media' => [[
                'media_type' => 'video',
                'mime_type' => 'video/webm',
                'path' => 'video.webm',
            ]],
        ]);

        $this->assertFalse($result->isPass);
        $this->assertContains(
            'Instagram videos must use MP4 or MOV.',
            $result->errors
        );
    }

    public function test_meta_snapshot_preflight_enforces_instagram_one_gigabyte_video_limit(): void
    {
        $atLimit = app(MetaSnapshotPreflightRules::class)->validate([
            'platform' => SocialPlatform::Instagram->value,
            'caption' => 'Video at limit',
            'target' => ['publication_type' => 'post'],
            'media' => [[
                'media_type' => 'video',
                'mime_type' => 'video/mp4',
                'path' => 'video.mp4',
                'size_bytes' => 1024 * 1024 * 1024,
            ]],
        ]);
        $overLimit = app(MetaSnapshotPreflightRules::class)->validate([
            'platform' => SocialPlatform::Instagram->value,
            'caption' => 'Video over limit',
            'target' => ['publication_type' => 'post'],
            'media' => [[
                'media_type' => 'video',
                'mime_type' => 'video/mp4',
                'path' => 'video.mp4',
                'size_bytes' => (1024 * 1024 * 1024) + 1,
            ]],
        ]);

        $this->assertTrue($atLimit->isPass);
        $this->assertFalse($overLimit->isPass);
        $this->assertContains(
            'Instagram videos cannot exceed 1 GB.',
            $overLimit->errors
        );
    }

    public function test_tiktok_frozen_target_uses_open_id_when_generic_provider_id_is_missing(): void
    {
        $account = ClientSocialAccount::factory()->create([
            'platform' => SocialPlatform::Tiktok,
            'provider_account_id' => null,
            'tiktok_open_id' => 'creator-open-id',
        ]);

        $target = app(
            ResolveFrozenPublicationTargetAction::class
        )->execute(SocialPlatform::Tiktok, $account);

        $this->assertSame('creator-open-id', $target['external_id']);
        $this->assertNull($target['profile_id']);
    }

    public function test_tiktok_photo_draft_uses_content_init_contract(): void
    {
        config([
            'services.tiktok.enable_photo_mode' => true,
            'services.tiktok.delivery_mode' => 'draft',
        ]);
        Http::fake([
            'https://open.tiktokapis.com/v2/post/publish/content/init/' => Http::response([
                'data' => ['publish_id' => 'photo-publish-1'],
                'error' => ['code' => 'ok', 'message' => ''],
            ], 200),
        ]);

        $result = app(TikTokContentPostingService::class)->initializePhotoPost(
            'token',
            [
                'title' => 'Title',
                'description' => 'Description',
                'photo_urls' => [
                    'https://verified.example/one.jpg',
                    'https://verified.example/two.jpg',
                ],
                'photo_cover_index' => 1,
            ],
            new PullFromUrlStrategy
        );

        $this->assertSame('photo-publish-1', $result['publish_id']);
        $this->assertSame('draft', $result['mode']);
        Http::assertSent(function (Request $request): bool {
            return $request['media_type'] === 'PHOTO'
                && $request['post_mode'] === 'MEDIA_UPLOAD'
                && $request['source_info']['source'] === 'PULL_FROM_URL'
                && $request['source_info']['photo_cover_index'] === 1
                && count($request['source_info']['photo_images']) === 2;
        });
    }

    public function test_tiktok_video_draft_rejects_success_response_without_publish_id(): void
    {
        config(['services.tiktok.delivery_mode' => 'draft']);
        Http::fake([
            'https://open.tiktokapis.com/v2/post/publish/inbox/video/init/' => Http::response([
                'data' => [],
                'error' => ['code' => 'ok', 'message' => ''],
            ], 200),
        ]);

        $this->expectException(TikTokApiException::class);
        $this->expectExceptionMessage('publish_id');

        app(TikTokContentPostingService::class)->initializeVideoPost(
            'token',
            ['video_url' => 'https://verified.example/video.mp4'],
            new PullFromUrlStrategy
        );
    }

    public function test_tiktok_video_direct_rejects_success_response_without_publish_id(): void
    {
        config([
            'services.tiktok.delivery_mode' => 'direct',
            'services.tiktok.direct_publish_enabled' => true,
        ]);
        Http::fake([
            'https://open.tiktokapis.com/v2/post/publish/video/init/' => Http::response([
                'data' => [],
                'error' => ['code' => 'ok', 'message' => ''],
            ], 200),
        ]);

        $this->expectException(TikTokApiException::class);
        $this->expectExceptionMessage('publish_id');

        app(TikTokContentPostingService::class)->initializeVideoPost(
            'token',
            ['video_url' => 'https://verified.example/video.mp4'],
            new PullFromUrlStrategy
        );
    }

    public function test_tiktok_snapshot_preflight_accepts_photo_carousel_and_rejects_mixed_media(): void
    {
        config(['services.tiktok.delivery_mode' => 'draft']);
        $rules = app(TikTokSnapshotPreflightRules::class);
        $photos = [
            'platform' => 'tiktok',
            'media' => [
                ['media_type' => 'image', 'mime_type' => 'image/jpeg'],
                ['media_type' => 'image', 'mime_type' => 'image/webp'],
            ],
        ];

        $this->assertTrue($rules->validate($photos)->isPass);

        $photos['media'][] = [
            'media_type' => 'video',
            'mime_type' => 'video/mp4',
        ];
        $mixed = $rules->validate($photos);
        $this->assertFalse($mixed->isPass);
        $this->assertContains(
            'TikTok does not support mixed photo and video posts.',
            $mixed->errors
        );
    }
}
