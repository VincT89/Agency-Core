<?php

namespace Tests\Feature\Social;

use App\Domain\Social\Actions\RetryMarketingCampaignPostPublicationAction;
use App\Domain\Social\DTOs\PublicationIntegrityResult;
use App\Domain\Social\Services\MarketingCampaignPostPublicationIntegrityVerifier;
use App\Enums\Social\PublicationStatus;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostPublication;
use App\Models\MarketingCampaignPostVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetryMarketingCampaignPostPublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_retries_failed_publication()
    {
        $post = MarketingCampaignPost::factory()->create();
        $version = MarketingCampaignPostVersion::factory()->create(['marketing_campaign_post_id' => $post->id]);

        $publication = MarketingCampaignPostPublication::factory()->create([
            'status' => PublicationStatus::Failed,
            'snapshot_schema_version' => 1,
            'attempt_count' => 1,
            'idempotency_key' => 'idemp123',
            'snapshot_hash' => 'hash123',
            'marketing_campaign_post_id' => $post->id,
            'marketing_campaign_post_version_id' => $version->id,
        ]);

        $verifier = \Mockery::mock(MarketingCampaignPostPublicationIntegrityVerifier::class);
        $verifier->shouldReceive('verify')->andReturn(new PublicationIntegrityResult(true));
        $this->app->instance(MarketingCampaignPostPublicationIntegrityVerifier::class, $verifier);

        $action = app(RetryMarketingCampaignPostPublicationAction::class);
        $newPublication = $action->execute($publication);

        $publication->refresh();

        $this->assertEquals(PublicationStatus::Superseded, $publication->status);

        $this->assertNotNull($newPublication);
        $this->assertEquals(PublicationStatus::Pending, $newPublication->status);
        $this->assertEquals(2, $newPublication->attempt_count);
        $this->assertEquals($publication->idempotency_key, $newPublication->idempotency_key);
        $this->assertEquals($publication->snapshot_hash, $newPublication->snapshot_hash);
        $this->assertEquals($publication->id, $newPublication->retry_of_publication_id);
        $this->assertNotNull($newPublication->stale_deadline_at);
        $this->assertTrue($newPublication->stale_deadline_at->isFuture());
    }

    public function test_it_cannot_retry_pending_publication()
    {
        $post = MarketingCampaignPost::factory()->create();
        $version = MarketingCampaignPostVersion::factory()->create(['marketing_campaign_post_id' => $post->id]);

        $publication = MarketingCampaignPostPublication::factory()->create([
            'status' => PublicationStatus::Pending,
            'snapshot_schema_version' => 1,
            'marketing_campaign_post_id' => $post->id,
            'marketing_campaign_post_version_id' => $version->id,
        ]);

        $verifier = \Mockery::mock(MarketingCampaignPostPublicationIntegrityVerifier::class);
        $verifier->shouldReceive('verify')->andReturn(new PublicationIntegrityResult(true));
        $this->app->instance(MarketingCampaignPostPublicationIntegrityVerifier::class, $verifier);

        $action = app(RetryMarketingCampaignPostPublicationAction::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Impossibile riprovare una pubblicazione nello stato pending. Consentito solo per Failed o NeedsManualReview.');

        $action->execute($publication);
    }
}
