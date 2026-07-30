<?php

namespace Tests\Feature\Social;

use App\Domain\Social\Services\SocialPublicationTelemetry;
use App\Enums\Social\PublicationFailureClassification;
use App\Enums\Social\PublicationStatus;
use App\Models\MarketingCampaignPostPublication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialPublicationTelemetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_context_has_the_uniform_operational_fields(): void
    {
        $publication = MarketingCampaignPostPublication::factory()->create([
            'status' => PublicationStatus::NeedsManualReview,
            'failure_classification' => PublicationFailureClassification::ManualReview,
            'correlation_id' => 'corr-123',
            'snapshot_hash' => str_repeat('a', 64),
            'attempt_count' => 2,
            'publishing_started_at' => now()->subSeconds(2),
        ]);

        $context = app(SocialPublicationTelemetry::class)->context(
            $publication,
            'publication.status_changed',
            PublicationStatus::Publishing->value
        );

        $this->assertSame([
            'correlation_id',
            'post_id',
            'version_id',
            'publication_id',
            'platform',
            'account_id',
            'snapshot_hash',
            'attempt_count',
            'event',
            'previous_status',
            'new_status',
            'duration_ms',
            'error_code',
        ], array_keys($context));
        $this->assertSame('corr-123', $context['correlation_id']);
        $this->assertSame($publication->id, $context['publication_id']);
        $this->assertSame(PublicationStatus::Publishing->value, $context['previous_status']);
        $this->assertSame(PublicationStatus::NeedsManualReview->value, $context['new_status']);
        $this->assertSame(
            PublicationFailureClassification::ManualReview->value,
            $context['error_code']
        );
        $this->assertGreaterThanOrEqual(1900, $context['duration_ms']);
    }
}
