<?php

namespace Tests\Feature\Social;

use App\Domain\Social\TikTok\TikTokContentPostingService;
use App\Domain\Social\TikTok\TikTokPostStatusService;
use App\Enums\Social\PublicationFailureClassification;
use App\Enums\Social\PublicationStatus;
use App\Enums\Social\SocialApiStatus;
use App\Enums\Social\SocialPlatform;
use App\Jobs\Social\TikTok\CheckTikTokPostStatusJob;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostPublication;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class TikTokPollingHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_429_is_persisted_and_released_as_temporary(): void
    {
        [$publication] = $this->publishingPublication();
        Http::fake([
            '*' => Http::response(
                ['error' => ['code' => 'rate_limit', 'message' => 'Slow down']],
                429,
                ['X-Tt-Logid' => 'req-429']
            ),
        ]);

        $job = (new CheckTikTokPostStatusJob($publication->id))
            ->withFakeQueueInteractions();
        $this->handle($job);

        $publication->refresh();
        $this->assertSame(PublicationStatus::Publishing, $publication->status);
        $this->assertSame(
            PublicationFailureClassification::Temporary,
            $publication->failure_classification
        );
        $this->assertSame(429, $publication->provider_last_response['http_status']);
        $this->assertSame('req-429', $publication->provider_last_response['request_id']);
        $job->assertReleased();
    }

    public function test_500_is_persisted_and_released_as_temporary(): void
    {
        [$publication] = $this->publishingPublication();
        Http::fake([
            '*' => Http::response(
                ['error' => ['code' => 'internal', 'message' => 'Unavailable']],
                503
            ),
        ]);

        $job = (new CheckTikTokPostStatusJob($publication->id))
            ->withFakeQueueInteractions();
        $this->handle($job);

        $publication->refresh();
        $this->assertSame(PublicationStatus::Publishing, $publication->status);
        $this->assertSame(503, $publication->provider_last_response['http_status']);
        $job->assertReleased();
    }

    public function test_auth_error_requires_manual_review_and_expires_token(): void
    {
        [$publication, $account] = $this->publishingPublication();
        Http::fake([
            '*' => Http::response(
                ['error' => ['code' => 'access_token_invalid', 'message' => 'Expired']],
                401,
                ['X-Request-Id' => 'req-auth']
            ),
        ]);

        $this->handle(new CheckTikTokPostStatusJob($publication->id));

        $this->assertSame(
            PublicationStatus::NeedsManualReview,
            $publication->fresh()->status
        );
        $this->assertSame(SocialApiStatus::TokenExpired, $account->fresh()->api_status);
        $this->assertSame(
            PublicationFailureClassification::ManualReview,
            $publication->fresh()->failure_classification
        );
    }

    public function test_non_retryable_4xx_is_permanent_failure(): void
    {
        [$publication] = $this->publishingPublication();
        Http::fake([
            '*' => Http::response(
                ['error' => ['code' => 'invalid_publish_id', 'message' => 'Invalid']],
                400
            ),
        ]);

        $this->handle(new CheckTikTokPostStatusJob($publication->id));

        $publication->refresh();
        $this->assertSame(PublicationStatus::Failed, $publication->status);
        $this->assertSame(
            PublicationFailureClassification::Permanent,
            $publication->failure_classification
        );
    }

    public function test_unknown_status_at_exhaustion_requires_manual_review(): void
    {
        [$publication] = $this->publishingPublication();
        Http::fake([
            '*' => Http::response([
                'data' => ['status' => 'UNKNOWN'],
                'error' => ['code' => 'ok'],
            ]),
        ]);

        $queueJob = Mockery::mock(QueueJob::class);
        $queueJob->shouldReceive('attempts')->andReturn(10);

        $job = new CheckTikTokPostStatusJob($publication->id);
        $job->setJob($queueJob);
        $this->handle($job);

        $publication->refresh();
        $this->assertSame(
            PublicationStatus::NeedsManualReview,
            $publication->status
        );
        $this->assertSame(
            PublicationFailureClassification::ManualReview,
            $publication->failure_classification
        );
    }

    private function publishingPublication(): array
    {
        $post = MarketingCampaignPost::factory()->create();
        $account = ClientSocialAccount::factory()->create([
            'client_id' => $post->campaign->client_id,
            'platform' => SocialPlatform::Tiktok,
            'api_status' => SocialApiStatus::Connected,
            'access_token' => 'valid-token',
        ]);
        $publication = MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'client_social_account_id' => $account->id,
            'platform' => SocialPlatform::Tiktok,
            'status' => PublicationStatus::Publishing,
            'external_task_id' => 'publish-123',
        ]);

        return [$publication, $account];
    }

    private function handle(CheckTikTokPostStatusJob $job): void
    {
        $job->handle(
            app(TikTokContentPostingService::class),
            app(TikTokPostStatusService::class)
        );
    }
}
