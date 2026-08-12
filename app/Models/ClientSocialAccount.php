<?php

namespace App\Models;

use App\Domain\Social\Actions\ResolveAssetAccessTokenAction;
use App\Enums\Social\AgencyConnectionStatus;
use App\Enums\Social\PublishingStatus;
use App\Enums\Social\SocialAccessMethod;
use App\Enums\Social\SocialAccessStatus;
use App\Enums\Social\SocialApiProvider;
use App\Enums\Social\SocialApiStatus;
use App\Enums\Social\SocialConnectionMode;
use App\Enums\Social\SocialConnectionStrategy;
use App\Enums\Social\SocialPlatform;
use App\Jobs\Social\CheckSocialAccountStatusJob;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientSocialAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'platform',
        'account_name',
        'account_url',
        'username',
        'account_exists',
        'access_method',
        'access_status',
        'connection_mode',
        'is_ready_to_publish',
        'access_verified_at',
        'access_verified_by',

        // Identificativi account specifici per piattaforma
        'business_manager_id',
        'business_center_id',
        'tiktok_account_id',
        'credential_location',

        // API and Asset Assignment
        'agency_social_asset_id',
        'connection_strategy',
        'assignment_changed_by',
        'assignment_changed_at',

        // Nuovi campi API e OAuth
        'provider_account_id',
        'provider_account_name',
        'facebook_page_id',
        'instagram_business_account_id',
        'tiktok_open_id',
        'scopes',
        'api_metadata',
        'connected_at',
        'last_api_check_at',
        'last_api_error',
        'publishing_capabilities',

        // Configurazione API e Token
        'api_provider',
        'api_status',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'refresh_token_expires_at',
        'api_notes',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_ready_to_publish' => 'boolean',
            'account_exists' => 'boolean',

            'platform' => SocialPlatform::class,
            'access_method' => SocialAccessMethod::class,
            'access_status' => SocialAccessStatus::class,
            'connection_mode' => SocialConnectionMode::class,
            'api_provider' => SocialApiProvider::class,
            'api_status' => SocialApiStatus::class,

            'scopes' => 'array',
            'api_metadata' => 'array',
            'publishing_capabilities' => 'array',

            'access_verified_at' => 'datetime',
            'token_expires_at' => 'datetime',
            'refresh_token_expires_at' => 'datetime',
            'connected_at' => 'datetime',
            'last_api_check_at' => 'datetime',

            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',

            'connection_strategy' => SocialConnectionStrategy::class,
            'assignment_changed_at' => 'datetime',
        ];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function agencyAsset()
    {
        return $this->belongsTo(AgencySocialAsset::class, 'agency_social_asset_id');
    }

    public function assignmentChangedBy()
    {
        return $this->belongsTo(User::class, 'assignment_changed_by');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'access_verified_by');
    }

    public function isReadyToPublish(): bool
    {
        if ($this->connection_strategy === SocialConnectionStrategy::AgencyOauth) {
            $asset = $this->agencyAsset;
            $connection = $asset?->connection()->first();

            return $asset !== null
                && $connection !== null
                && $asset->is_active
                && $asset->is_assignable
                && $asset->status === AgencyConnectionStatus::Connected
                && $asset->publishing_status === PublishingStatus::Ready
                && $connection->status === AgencyConnectionStatus::Connected
                && ! $connection->requires_reauth
                && filled(app(ResolveAssetAccessTokenAction::class)->execute($asset));
        }

        if ($this->isApiConnected()) {
            if ($this->isTikTok()) {
                return $this->canPublishTikTokVideo() || $this->canPublishTikTokPhoto();
            }

            if (! is_array($this->publishing_capabilities) || empty($this->publishing_capabilities)) {
                return false;
            }

            foreach ($this->publishing_capabilities as $capability) {
                if ((isset($capability['enabled']) && $capability['enabled'] === true) ||
                    (isset($capability['can_publish_video']) && $capability['can_publish_video'] === true)) {
                    return true;
                }
            }

            return false;
        }

        // Per gli account manuali ci basiamo sui flag manuali impostati dall'operatore
        return $this->is_ready_to_publish
            && $this->access_status === SocialAccessStatus::ReadyToPublish;
    }

    public function canPublishTikTokVideo(): bool
    {
        if (! $this->isTikTok() || ! $this->isApiConnected()) {
            return false;
        }

        $capabilities = $this->publishing_capabilities['tiktok'] ?? [];
        $scopes = is_array($this->scopes) ? $this->scopes : [];

        return match ((string) config('services.tiktok.delivery_mode', 'disabled')) {
            'draft' => in_array('video.upload', $scopes, true)
                && ($capabilities['can_upload_video_draft'] ?? false) === true,
            'direct' => (bool) config('services.tiktok.direct_publish_enabled', false)
                && in_array('video.publish', $scopes, true)
                && ($capabilities['can_direct_publish_video'] ?? false) === true,
            default => false,
        };
    }

    public function canPublishTikTokPhoto(): bool
    {
        if (! $this->isTikTok() || ! $this->isApiConnected() || config('services.tiktok.enable_photo_mode') !== true) {
            return false;
        }

        $capabilities = $this->publishing_capabilities['tiktok'] ?? [];
        if (($capabilities['can_publish_photo'] ?? false) !== true) {
            return false;
        }

        $scopes = is_array($this->scopes) ? $this->scopes : [];

        return match ((string) config('services.tiktok.delivery_mode', 'disabled')) {
            'draft' => in_array('video.upload', $scopes, true),
            'direct' => (bool) config('services.tiktok.direct_publish_enabled', false)
                && in_array('video.publish', $scopes, true),
            default => false,
        };
    }

    public function verifyPublishingReadiness(): void
    {
        if (! $this->last_api_check_at || $this->last_api_check_at->diffInHours(now()) > 24) {
            // Se è stale (più vecchio di 24 ore), dispatcha il job asincrono senza bloccare la UI
            dispatch(new CheckSocialAccountStatusJob($this->id));
        }
    }

    public function isApiConnected(): bool
    {
        return $this->api_status === SocialApiStatus::Connected
            && filled($this->access_token)
            && (
                blank($this->token_expires_at)
                || $this->token_expires_at->isFuture()
            );
    }

    public function requiresManualPublishing(): bool
    {
        return ! $this->isApiConnected();
    }

    public function isMetaPlatform(): bool
    {
        return in_array($this->platform, [SocialPlatform::Facebook, SocialPlatform::Instagram]);
    }

    public function isTikTok(): bool
    {
        return $this->platform === SocialPlatform::Tiktok;
    }
}
