<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingCampaignPostMedia extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'source_size_bytes' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (MarketingCampaignPostMedia $media) {
            app(\App\Domain\Social\Services\HistoricalMediaProtectionService::class)->assertDeletable($media);
        });
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaignPost::class, 'marketing_campaign_post_id');
    }

    public static function isVideoMime(string $mime): bool
    {
        return str_starts_with(strtolower($mime), 'video/');
    }

    public function isVideo(): bool
    {
        if ($this->media_type === 'video') return true;
        if ($this->mime_type && self::isVideoMime($this->mime_type)) return true;
        return false;
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

    public function versions(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            MarketingCampaignPostVersion::class,
            'marketing_campaign_post_version_media',
            'marketing_campaign_post_media_id',
            'marketing_campaign_post_version_id'
        )
            ->withPivot('sort_order')
            ->withTimestamps();
    }
}
