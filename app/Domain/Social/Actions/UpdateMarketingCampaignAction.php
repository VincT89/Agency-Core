<?php

namespace App\Domain\Social\Actions;

use App\Models\MarketingCampaign;
use App\Enums\Social\MarketingCampaignStatus;
use Illuminate\Support\Carbon;

class UpdateMarketingCampaignAction
{
    /**
     * @param array<string, mixed> $data
     */
    public function execute(MarketingCampaign $campaign, array $data): MarketingCampaign
    {
        $campaign->update([
            'client_id' => $data['client_id'] ?? $campaign->client_id,
            'name' => $data['name'] ?? $campaign->name,
            'description' => $data['description'] ?? $campaign->description,
            'status' => isset($data['status']) ? MarketingCampaignStatus::tryFrom($data['status']) : $campaign->status,
            'starts_at' => isset($data['starts_at']) && $data['starts_at'] ? Carbon::parse($data['starts_at']) : null,
            'ends_at' => isset($data['ends_at']) && $data['ends_at'] ? Carbon::parse($data['ends_at']) : null,
            'monthly_fee' => $data['monthly_fee'] ?? $campaign->monthly_fee,
            'notes' => $data['notes'] ?? $campaign->notes,
        ]);

        return $campaign->fresh();
    }
}
