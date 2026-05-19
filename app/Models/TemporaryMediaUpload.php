<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemporaryMediaUpload extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_path',
        'temp_path',
        'hash',
        'marketing_campaign_post_id',
        'cleanup_status',
        'correlation_id',
    ];

    public function marketingCampaignPost(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaignPost::class);
    }
}
