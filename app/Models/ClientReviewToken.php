<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientReviewToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'reviewable_id',
        'reviewable_type',
        'marketing_campaign_post_version_id',
        'token',
        'expires_at',
        'used_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return ! is_null($this->used_at);
    }

    public function markAsUsed(): void
    {
        $this->forceFill(['used_at' => now()])->save();
    }

    public function reviewable()
    {
        return $this->morphTo();
    }
}
