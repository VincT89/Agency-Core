<?php

namespace Tests\Feature\Social;

use App\Enums\Social\PublicationStatus;
use App\Models\MarketingCampaignPostPublication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditSocialRuntimeCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_can_fail_when_actionable_publications_exist(): void
    {
        MarketingCampaignPostPublication::factory()->create([
            'status' => PublicationStatus::NeedsManualReview,
        ]);

        $this->artisan('social:audit-runtime', [
            '--fail-on-actionable' => true,
        ])
            ->expectsOutputToContain('Revisioni manuali: 1')
            ->assertFailed();
    }

    public function test_audit_succeeds_without_actionable_publications(): void
    {
        MarketingCampaignPostPublication::factory()->create([
            'status' => PublicationStatus::Published,
        ]);

        $this->artisan('social:audit-runtime', [
            '--fail-on-actionable' => true,
        ])->assertSuccessful();
    }
}
