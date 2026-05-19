<?php

namespace App\Models;

use App\Enums\Social\SocialPlatform;
use App\Enums\Social\SocialAccessMethod;
use App\Enums\Social\SocialAccessStatus;
use App\Enums\Social\SocialApiProvider;
use App\Enums\Social\SocialApiStatus;
use App\Enums\Social\SocialConnectionMode;
use Illuminate\Database\Eloquent\Model;

class ClientSocialAccount extends Model
{
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
            'connected_at' => 'datetime',
            'last_api_check_at' => 'datetime',
            
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            
            'connection_strategy' => \App\Enums\Social\SocialConnectionStrategy::class,
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
        if ($this->connection_mode === SocialConnectionMode::Oauth) {
            // Un account OAuth è "pronto a pubblicare" solo se ha un token API valido,
            // non ha uno stato error/disconnected/revoked, e ha almeno una capability attiva.
            if (!$this->isApiConnected() || !is_array($this->publishing_capabilities) || empty($this->publishing_capabilities)) {
                return false;
            }

            foreach ($this->publishing_capabilities as $capability) {
                if (isset($capability['enabled']) && $capability['enabled'] === true) {
                    return true;
                }
            }

            return false;
        }

        // Per gli account manuali ci basiamo sui flag manuali impostati dall'operatore
        return $this->is_ready_to_publish 
            && $this->access_status === SocialAccessStatus::ReadyToPublish;
    }

    public function verifyPublishingReadiness(): void
    {
        if (!$this->last_api_check_at || $this->last_api_check_at->diffInHours(now()) > 24) {
            // Se è stale (più vecchio di 24 ore), dispatcha il job asincrono senza bloccare la UI
            dispatch(new \App\Jobs\Social\CheckSocialAccountStatusJob($this->id));
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
