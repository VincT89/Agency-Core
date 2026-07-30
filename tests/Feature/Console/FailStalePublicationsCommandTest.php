<?php

namespace Tests\Feature\Console;

use App\Enums\Social\PublicationFailureClassification;
use App\Enums\Social\PublicationStatus;
use App\Models\MarketingCampaignPostPublication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FailStalePublicationsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_is_failed_but_dispatched_publication_requires_review(): void
    {
        $pending = MarketingCampaignPostPublication::factory()->create([
            'status' => PublicationStatus::Pending,
            'stale_deadline_at' => now()->subMinute(),
        ]);
        $publishing = MarketingCampaignPostPublication::factory()->create([
            'status' => PublicationStatus::Publishing,
            'stale_deadline_at' => now()->subMinute(),
        ]);
        $future = MarketingCampaignPostPublication::factory()->create([
            'status' => PublicationStatus::Pending,
            'stale_deadline_at' => now()->addMinute(),
        ]);

        $this->artisan('social:fail-stale-publications')
            ->assertSuccessful();

        $this->assertSame(
            PublicationStatus::Failed,
            $pending->fresh()->status
        );
        $this->assertSame(
            PublicationFailureClassification::Temporary,
            $pending->fresh()->failure_classification
        );
        $this->assertSame(
            PublicationStatus::NeedsManualReview,
            $publishing->fresh()->status
        );
        $this->assertSame(
            PublicationFailureClassification::ManualReview,
            $publishing->fresh()->failure_classification
        );
        $this->assertSame(
            PublicationStatus::Pending,
            $future->fresh()->status
        );
    }
}
