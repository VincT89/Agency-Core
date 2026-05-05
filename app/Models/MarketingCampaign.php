<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\Social\MarketingCampaignStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingCampaign extends Model
{
    protected $guarded = [];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
        'monthly_fee' => 'decimal:2',
        'status' => MarketingCampaignStatus::class,
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(MarketingCampaignPost::class);
    }

    public function periods(): HasMany
    {
        return $this->hasMany(MarketingCampaignPeriod::class);
    }

    public function extras(): HasMany
    {
        return $this->hasMany(MarketingCampaignExtra::class);
    }
}
