<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingCampaignPostPublication extends Model
{
    protected $fillable = [
        'marketing_campaign_post_id',
        'client_social_account_id',
        'platform',
        'status',
        'meta_processing_state',
        'external_post_id',
        'external_container_id',
        'external_permalink',
        'payload_snapshot',
        'response_snapshot',
        'provider_state_payload',
        'provider_last_response',
        'error_message',
        'published_at',
        'correlation_id',
    ];

    protected function casts(): array
    {
        return [
            'payload_snapshot' => 'array',
            'response_snapshot' => 'array',
            'provider_state_payload' => 'array',
            'provider_last_response' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaignPost::class, 'marketing_campaign_post_id');
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(ClientSocialAccount::class, 'client_social_account_id');
    }
}
