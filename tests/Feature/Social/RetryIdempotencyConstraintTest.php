<?php

namespace Tests\Feature\Social;

use App\Domain\Social\Actions\RetryMarketingCampaignPostPublicationAction;
use App\Domain\Social\DTOs\PublicationIntegrityResult;
use App\Domain\Social\Services\MarketingCampaignPostPublicationIntegrityVerifier;
use App\Enums\Social\PublicationStatus;
use App\Models\MarketingCampaignPostPublication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Mockery;

class RetryIdempotencyConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_retry_if_snapshot_lacks_schema_version()
    {
        $publication = MarketingCampaignPostPublication::factory()->create([
            'status' => PublicationStatus::Failed->value,
            'snapshot_schema_version' => null,
        ]);

        $verifier = Mockery::mock(MarketingCampaignPostPublicationIntegrityVerifier::class);
        $action = new RetryMarketingCampaignPostPublicationAction($verifier);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Impossibile riprovare una publication legacy priva di snapshot canonico.");

        $action->execute($publication);
    }

    public function test_cannot_retry_if_integrity_check_fails()
    {
        $publication = MarketingCampaignPostPublication::factory()->create([
            'status' => PublicationStatus::Failed->value,
            'snapshot_schema_version' => 1,
        ]);

        $verifier = Mockery::mock(MarketingCampaignPostPublicationIntegrityVerifier::class);
        $verifier->shouldReceive('verify')->once()->andReturn(new PublicationIntegrityResult(false, ['Hash mismatch']));

        $action = new RetryMarketingCampaignPostPublicationAction($verifier);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Impossibile riprovare: Snapshot non integro. Errori: Hash mismatch");

        $action->execute($publication);
    }

    public function test_cannot_retry_successful_publication()
    {
        $publication = MarketingCampaignPostPublication::factory()->create([
            'status' => PublicationStatus::Published->value,
            'snapshot_schema_version' => 1,
            'idempotency_key' => Str::uuid()->toString(),
            'snapshot_hash' => 'hash',
        ]);

        $verifier = Mockery::mock(MarketingCampaignPostPublicationIntegrityVerifier::class);
        $verifier->shouldReceive('verify')->once()->andReturn(new PublicationIntegrityResult(true));

        $action = new RetryMarketingCampaignPostPublicationAction($verifier);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Impossibile riprovare una pubblicazione nello stato " . PublicationStatus::Published->value . ". Consentito solo per Failed o NeedsManualReview.");

        $action->execute($publication);
    }

    public function test_retry_creates_superseding_publication()
    {
        $publication = MarketingCampaignPostPublication::factory()->create([
            'status' => PublicationStatus::Failed->value,
            'snapshot_schema_version' => 1,
            'idempotency_key' => Str::uuid()->toString(),
            'snapshot_hash' => 'hash',
            'attempt_count' => 1,
        ]);

        $verifier = Mockery::mock(MarketingCampaignPostPublicationIntegrityVerifier::class);
        $verifier->shouldReceive('verify')->once()->andReturn(new PublicationIntegrityResult(true));

        $action = new RetryMarketingCampaignPostPublicationAction($verifier);
        
        $newPublication = $action->execute($publication);

        $publication->refresh();
        $this->assertEquals(PublicationStatus::Superseded, $publication->status);
        
        $this->assertEquals(PublicationStatus::Pending, $newPublication->status);
        $this->assertEquals(2, $newPublication->attempt_count);
        $this->assertEquals($publication->id, $newPublication->retry_of_publication_id);
    }
}
