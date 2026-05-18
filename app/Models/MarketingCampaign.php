<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\Social\MarketingCampaignStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;

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

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function isActive(): bool
    {
        $activeStatuses = [
            \App\Enums\Social\MarketingCampaignPeriodStatus::Active->value,
            \App\Enums\Social\MarketingCampaignPeriodStatus::Planned->value,
        ];

        if ($this->relationLoaded('periods')) {
            return $this->periods->contains(function ($period) use ($activeStatuses) {
                $statusValue = $period->status instanceof \BackedEnum ? $period->status->value : $period->status;
                return in_array($statusValue, $activeStatuses);
            });
        }

        return $this->periods()
            ->whereIn('status', $activeStatuses)
            ->exists();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->canManageSystem() || $user->isMarketing()) {
            return $query;
        }

        return $query->whereHas('client.projects.users', function ($q) use ($user) {
            $q->where('users.id', $user->id);
        });
    }
}
