<?php

namespace App\Models;

use App\Domain\Social\Exceptions\HistoricalPostProtectedException;
use App\Domain\Social\Services\MarketingCampaignPostMediaUrlResolver;
use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\Social\MarketingCampaignPostType;
use App\Enums\Social\PublicationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\URL;

class MarketingCampaignPost extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'scheduled_date' => 'date',
        'published_at' => 'datetime',
        'ai_analysis_enabled' => 'boolean',
        'submitted_to_n8n_at' => 'datetime',
        'approved_payload_snapshot' => 'array',
        'n8n_internal_context' => 'array',
        'status' => MarketingCampaignPostStatus::class,
        'n8n_previous_status' => MarketingCampaignPostStatus::class,
        'content_type' => MarketingCampaignPostType::class,
        'generated_at' => 'datetime',
        'n8n_completed_at' => 'datetime',
        'sent_to_client_at' => 'datetime',
        'client_approved_at' => 'datetime',
        'publishing_platforms' => 'array',
    ];

    protected static function booted()
    {
        static::deleting(function (MarketingCampaignPost $post) {
            if ($post->versions()->exists() || $post->publications()->exists()) {
                throw HistoricalPostProtectedException::forPost($post);
            }
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'marketing_campaign_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(MarketingCampaignPostVersion::class);
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(MarketingCampaignPostVersion::class, 'id', 'current_version_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(MarketingCampaignPostComment::class);
    }

    public function internalComments(): HasMany
    {
        return $this->comments()->where('visibility', 'internal');
    }

    public function clientComments(): HasMany
    {
        return $this->comments()->where('visibility', '!=', 'internal');
    }

    public function reviewTokens(): MorphMany
    {
        return $this->morphMany(ClientReviewToken::class, 'reviewable');
    }

    public function mediaItems(): HasMany
    {
        return $this->hasMany(MarketingCampaignPostMedia::class);
    }

    public function orderedMediaItems(): HasMany
    {
        return $this->mediaItems()->orderBy('sort_order', 'asc');
    }

    public function primaryMedia(): HasOne
    {
        return $this->hasOne(MarketingCampaignPostMedia::class)->orderBy('sort_order', 'asc');
    }

    public function canRegenerate(): bool
    {
        return in_array($this->status, [
            MarketingCampaignPostStatus::Generated,
            MarketingCampaignPostStatus::ReadyForClient,
            MarketingCampaignPostStatus::SentToClient,
            MarketingCampaignPostStatus::ClientChangesRequested,
            MarketingCampaignPostStatus::Draft,
        ], true);
    }

    public function getPrimaryMediaItem(): ?MarketingCampaignPostMedia
    {
        if ($this->relationLoaded('orderedMediaItems')) {
            return $this->orderedMediaItems->first();
        }

        if ($this->relationLoaded('mediaItems')) {
            return $this->mediaItems->sortBy('sort_order')->first();
        }

        return $this->primaryMedia;
    }

    public function getMediaUrlAttribute(): ?string
    {
        $primary = $this->getPrimaryMediaItem();

        if ($primary) {
            return app(
                MarketingCampaignPostMediaUrlResolver::class
            )->deliveryUrl($primary);
        }

        if ($this->media_source === 'nextcloud') {
            return $this->nextcloud_share_url
                ? rtrim($this->nextcloud_share_url, '/').'/download'
                : null;
        }

        if (! $this->media_path) {
            return null;
        }

        return URL::temporarySignedRoute(
            'media.marketing-campaign-posts',
            now()->addMinutes(
                (int) config('services.tiktok.media_url_ttl', 1440)
            ),
            ['path' => $this->media_path]
        );
    }

    public function getPreviewUrlAttribute(): ?string
    {
        $primary = $this->getPrimaryMediaItem();

        if ($primary && $primary->source === 'nextcloud' && $primary->nextcloud_path) {
            return route('nextcloud.preview', [
                'path' => $primary->nextcloud_path,
                'w' => 800,
                'h' => 800,
            ]);
        }

        if ($this->media_source === 'nextcloud' && $this->nextcloud_path) {
            return route('nextcloud.preview', [
                'path' => $this->nextcloud_path,
                'w' => 800,
                'h' => 800,
            ]);
        }

        return $this->media_url;
    }

    public function publications()
    {
        return $this->hasMany(MarketingCampaignPostPublication::class, 'marketing_campaign_post_id');
    }

    public function successfulPublications(): HasMany
    {
        return $this->publications()
            ->where('status', PublicationStatus::Published->value)
            ->whereNotNull('published_at');
    }

    public function scopeCalendarEligible(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query
                ->whereNotNull('scheduled_date')
                ->orWhereNotNull('published_at')
                ->orWhereHas('successfulPublications');
        });
    }

    public function getResolvedCaptionAttribute(): string
    {
        return $this->currentVersion?->caption ?? $this->description ?? '';
    }

    public function getResolvedTitleAttribute(): string
    {
        return $this->currentVersion?->title ?? $this->title ?? '';
    }
}
