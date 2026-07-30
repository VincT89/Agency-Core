<?php

namespace Tests\Feature\Livewire\Admin\Social;

use App\Enums\Social\PublicationStatus;
use App\Enums\UserRole;
use App\Livewire\Admin\Social\SocialRuntimeDashboard;
use App\Models\MarketingCampaignPostPublication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SocialRuntimeDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_and_manual_review_publications_are_kept_in_their_own_queues(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);
        $failed = MarketingCampaignPostPublication::factory()->create([
            'status' => PublicationStatus::Failed,
            'updated_at' => now(),
        ]);
        $manualReview = MarketingCampaignPostPublication::factory()->create([
            'status' => PublicationStatus::NeedsManualReview,
            'updated_at' => now(),
        ]);
        MarketingCampaignPostPublication::factory()->create([
            'status' => PublicationStatus::Failed,
            'updated_at' => now()->subDays(2),
        ]);

        $component = Livewire::actingAs($admin)
            ->test(SocialRuntimeDashboard::class);

        $this->assertSame(
            [$failed->id],
            $component->viewData('failedLast24h')->pluck('id')->all()
        );
        $this->assertSame(
            [$manualReview->id],
            $component->viewData('needsManualReview')->pluck('id')->all()
        );
    }
}
