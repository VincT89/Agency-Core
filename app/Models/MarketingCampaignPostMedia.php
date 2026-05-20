<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingCampaignPostMedia extends Model
{
    protected $guarded = [];

    public function post(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaignPost::class, 'marketing_campaign_post_id');
    }

    public static function isVideoMime(string $mime): bool
    {
        return str_starts_with(strtolower($mime), 'video/');
    }

    public static function detectMediaType(string $mime): string
    {
        $mime = strtolower($mime);
        if (self::isVideoMime($mime)) {
            return 'video';
        }
        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }
        return 'unknown';
    }
}
