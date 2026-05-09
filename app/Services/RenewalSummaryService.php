<?php

namespace App\Services;

use App\Models\HostingService;
use Illuminate\Support\Carbon;

class RenewalSummaryService
{
    public function getExpiringCount(int $days = 30): int
    {
        $now = now()->startOfDay();
        return HostingService::where('renewal_date', '>=', $now)
            ->where('renewal_date', '<=', $now->copy()->addDays($days))
            ->where('status', 'active')
            ->count();
    }

    public function getExpiredCount(): int
    {
        return HostingService::where('renewal_date', '<', now()->startOfDay())
            ->count();
    }
}
