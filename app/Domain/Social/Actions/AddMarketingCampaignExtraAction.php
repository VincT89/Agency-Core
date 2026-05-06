<?php

namespace App\Domain\Social\Actions;

use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignExtra;
use App\Enums\Social\MarketingCampaignExtraStatus;
use Illuminate\Support\Carbon;

class AddMarketingCampaignExtraAction
{
    /**
     * @param array<string, mixed> $data
     */
    public function execute(MarketingCampaign $campaign, array $data): MarketingCampaignExtra
    {
        return $campaign->extras()->create([
            'description' => $data['description'],
            'amount' => $data['amount'],
            'occurred_on' => isset($data['occurred_on']) && $data['occurred_on'] ? Carbon::parse($data['occurred_on']) : null,
            'status' => MarketingCampaignExtraStatus::Pending,
        ]);
    }
}
