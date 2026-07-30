<?php

namespace App\Livewire\Admin\Social;

use App\Enums\Social\PublicationStatus;
use App\Models\MarketingCampaignPostPublication;
use Livewire\Component;

class SocialRuntimeDashboard extends Component
{
    public function mount(): void
    {
        $this->authorize('manage_social_operations');
    }

    public function render()
    {
        $pendingAndPublishing = MarketingCampaignPostPublication::with('post', 'socialAccount')
            ->whereIn('status', [PublicationStatus::Pending, PublicationStatus::Publishing])
            ->orderBy('created_at', 'desc')
            ->get();

        $failedLast24h = MarketingCampaignPostPublication::with('post', 'socialAccount')
            ->where('status', PublicationStatus::Failed)
            ->where('updated_at', '>=', now()->subHours(24))
            ->orderBy('updated_at', 'desc')
            ->get();

        $needsManualReview = MarketingCampaignPostPublication::with('post', 'socialAccount')
            ->where('status', PublicationStatus::NeedsManualReview)
            ->latest()
            ->take(10)
            ->get();

        // Retry counts aggregated by provider
        $retryStats = MarketingCampaignPostPublication::selectRaw('platform, SUM(attempt_count) as total_attempts, SUM(poll_count) as total_polls')
            ->groupBy('platform')
            ->get();

        return view('livewire.admin.social.social-runtime-dashboard', [
            'pendingAndPublishing' => $pendingAndPublishing,
            'failedLast24h' => $failedLast24h,
            'needsManualReview' => $needsManualReview,
            'retryStats' => $retryStats,
        ]);
    }
}
