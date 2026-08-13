<?php

namespace Tests\Feature\Social;

use App\Domain\Social\Actions\ProcessInstagramContainerAction;
use App\Domain\Social\Actions\ResolveAssetAccessTokenAction;
use App\Domain\Social\Actions\SyncMarketingCampaignPostPublicationStatusAction;
use App\Domain\Social\Services\InstagramContainerStatusResult;
use App\Domain\Social\Services\InstagramContainerStatusService;
use App\Enums\Social\PublicationFailureClassification;
use App\Enums\Social\PublicationStatus;
use App\Enums\Social\SocialPlatform;
use App\Exceptions\Social\ContainerProcessingException;
use App\Jobs\Social\CheckInstagramContainerStatusJob;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostPublication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class InstagramPollingHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_passes_the_publication_id_to_the_action(): void
    {
        $publication = $this->publication();
        $action = Mockery::mock(ProcessInstagramContainerAction::class);
        $action->shouldReceive('execute')
            ->once()
            ->with($publication->id);

        (new CheckInstagramContainerStatusJob($publication->id))->handle($action);
    }

    public function test_ambiguous_publish_exception_requires_manual_review(): void
    {
        $publication = $this->publication();
        $service = Mockery::mock(InstagramContainerStatusService::class);
        $service->shouldReceive('getContainerStatus')
            ->once()
            ->andReturn(new InstagramContainerStatusResult(
                status: 'FINISHED',
                isPermanentError: false,
                errorMessage: null,
                responseData: ['status_code' => 'FINISHED']
            ));
        $service->shouldReceive('publishContainer')
            ->once()
            ->andThrow(new \RuntimeException('Connection lost after media_publish'));

        $sync = Mockery::mock(SyncMarketingCampaignPostPublicationStatusAction::class);
        $sync->shouldReceive('execute')->once();
        $resolveToken = Mockery::mock(ResolveAssetAccessTokenAction::class);

        $action = new ProcessInstagramContainerAction(
            $service,
            $sync,
            $resolveToken
        );
        $action->execute($publication->id);

        $publication->refresh();
        $this->assertSame(
            PublicationStatus::NeedsManualReview,
            $publication->status
        );
        $this->assertSame(
            PublicationFailureClassification::ManualReview,
            $publication->failure_classification
        );
        $this->assertArrayHasKey(
            'publish_claim_uuid',
            $publication->provider_state_payload
        );
    }

    public function test_successful_publish_persists_the_instagram_permalink(): void
    {
        $publication = $this->publication();
        $service = Mockery::mock(InstagramContainerStatusService::class);
        $service->shouldReceive('getContainerStatus')
            ->once()
            ->andReturn(new InstagramContainerStatusResult(
                status: 'FINISHED',
                isPermanentError: false,
                errorMessage: null,
                responseData: ['status_code' => 'FINISHED']
            ));
        $service->shouldReceive('publishContainer')
            ->once()
            ->andReturn(['id' => 'ig-media-123']);
        $service->shouldReceive('getMediaPermalink')
            ->once()
            ->with(
                'ig-media-123',
                'valid-token',
                $publication->correlation_id
            )
            ->andReturn('https://www.instagram.com/p/example123/');

        $sync = Mockery::mock(SyncMarketingCampaignPostPublicationStatusAction::class);
        $sync->shouldReceive('execute')->once();
        $resolveToken = Mockery::mock(ResolveAssetAccessTokenAction::class);

        (new ProcessInstagramContainerAction($service, $sync, $resolveToken))
            ->execute($publication->id);

        $publication->refresh();
        $this->assertSame(PublicationStatus::Published, $publication->status);
        $this->assertSame('ig-media-123', $publication->external_post_id);
        $this->assertSame(
            'https://www.instagram.com/p/example123/',
            $publication->external_permalink
        );
        $this->assertSame(
            $publication->external_permalink,
            $publication->resolved_external_permalink
        );
    }

    public function test_failed_callback_does_not_overwrite_published_state(): void
    {
        $publication = $this->publication([
            'status' => PublicationStatus::Published,
        ]);

        (new CheckInstagramContainerStatusJob($publication->id))
            ->failed(new \RuntimeException('late failure'));

        $this->assertSame(PublicationStatus::Published, $publication->fresh()->status);
    }

    public function test_failed_callback_preserves_ambiguous_claim_as_manual_review(): void
    {
        $publication = $this->publication([
            'provider_state_payload' => [
                'phase' => 'single_container_processing',
                'publish_claim_uuid' => 'claim-123',
                'publish_claim_expires_at' => now()->addMinute()->toDateTimeString(),
            ],
        ]);

        (new CheckInstagramContainerStatusJob($publication->id))
            ->failed(new ContainerProcessingException('still processing'));

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

    private function publication(array $overrides = []): MarketingCampaignPostPublication
    {
        $post = MarketingCampaignPost::factory()->create();
        $account = ClientSocialAccount::factory()->create([
            'client_id' => $post->campaign->client_id,
            'platform' => SocialPlatform::Instagram,
            'provider_account_id' => 'ig-account-123',
            'access_token' => 'valid-token',
        ]);

        return MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'client_social_account_id' => $account->id,
            'platform' => SocialPlatform::Instagram,
            'status' => PublicationStatus::Publishing,
            'external_container_id' => 'container-123',
            'provider_state_payload' => [
                'phase' => 'single_container_processing',
            ],
            ...$overrides,
        ]);
    }
}
