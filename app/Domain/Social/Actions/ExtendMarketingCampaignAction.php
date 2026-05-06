<?php

namespace App\Domain\Social\Actions;

use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPeriod;
use App\Enums\Social\MarketingCampaignPeriodStatus;
use App\Enums\Social\MarketingCampaignStatus;
use Illuminate\Support\Carbon;

class ExtendMarketingCampaignAction
{
    /**
     * @param array<string, mixed> $data
     */
    public function execute(MarketingCampaign $campaign, array $data): MarketingCampaignPeriod
    {
        $period = $campaign->periods()->create([
            'from_date' => Carbon::parse($data['from_date']),
            'to_date' => isset($data['to_date']) && $data['to_date'] ? Carbon::parse($data['to_date']) : null,
            'amount' => $data['amount'] ?? $campaign->monthly_fee,
            'description' => $data['description'] ?? 'Prolungamento campagna',
            'status' => MarketingCampaignPeriodStatus::Planned,
        ]);

        if (isset($data['to_date']) && $data['to_date']) {
            $campaign->update(['ends_at' => Carbon::parse($data['to_date'])]);
        }

        if (in_array($campaign->status, [MarketingCampaignStatus::Draft, MarketingCampaignStatus::Closed])) {
            $campaign->update(['status' => MarketingCampaignStatus::Active]);
        }

        return $period;
    }
}
