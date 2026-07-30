<?php

namespace Tests\Feature\Social;

use App\Domain\Social\Actions\RetryMarketingCampaignPostPublicationAction;
use App\Models\MarketingCampaignPostPublication;
use App\Enums\Social\PublicationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetryMarketingCampaignPostPublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_retries_failed_publication()
    {
        $post = \App\Models\MarketingCampaignPost::factory()->create();
        $version = \App\Models\MarketingCampaignPostVersion::factory()->create(['marketing_campaign_post_id' => $post->id]);
        
        $publication = MarketingCampaignPostPublication::factory()->create([
            'status' => PublicationStatus::Failed,
            'snapshot_schema_version' => 1,
            'attempt_count' => 1,
            'idempotency_key' => 'idemp123',
            'snapshot_hash' => 'hash123',
            'marketing_campaign_post_id' => $post->id,
            'marketing_campaign_post_version_id' => $version->id,
        ]);

        $verifier = \Mockery::mock(\App\Domain\Social\Services\MarketingCampaignPostPublicationIntegrityVerifier::class);
        $verifier->shouldReceive('verify')->andReturn(new \App\Domain\Social\DTOs\PublicationIntegrityResult(true));
        $this->app->instance(\App\Domain\Social\Services\MarketingCampaignPostPublicationIntegrityVerifier::class, $verifier);

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
    }

    public function test_it_cannot_retry_pending_publication()
    {
        $post = \App\Models\MarketingCampaignPost::factory()->create();
        $version = \App\Models\MarketingCampaignPostVersion::factory()->create(['marketing_campaign_post_id' => $post->id]);

        $publication = MarketingCampaignPostPublication::factory()->create([
            'status' => PublicationStatus::Pending,
            'snapshot_schema_version' => 1,
            'marketing_campaign_post_id' => $post->id,
            'marketing_campaign_post_version_id' => $version->id,
        ]);
        
        $verifier = \Mockery::mock(\App\Domain\Social\Services\MarketingCampaignPostPublicationIntegrityVerifier::class);
        $verifier->shouldReceive('verify')->andReturn(new \App\Domain\Social\DTOs\PublicationIntegrityResult(true));
        $this->app->instance(\App\Domain\Social\Services\MarketingCampaignPostPublicationIntegrityVerifier::class, $verifier);

        $action = app(RetryMarketingCampaignPostPublicationAction::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Impossibile riprovare una pubblicazione nello stato pending. Consentito solo per Failed o NeedsManualReview.");

        $action->execute($publication);
    }
}
