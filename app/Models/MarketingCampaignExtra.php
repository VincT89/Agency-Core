<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\Social\MarketingCampaignExtraStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingCampaignExtra extends Model
{
    protected $guarded = [];

    protected $casts = [
        'occurred_on' => 'date',
        'amount' => 'decimal:2',
        'status' => MarketingCampaignExtraStatus::class,
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'marketing_campaign_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
