<?php

namespace App\Models;

use App\Enums\Social\SocialPlatform;
use App\Enums\Social\SocialAccessMethod;
use App\Enums\Social\SocialAccessStatus;
use App\Enums\Social\SocialApiProvider;
use App\Enums\Social\SocialApiStatus;
use Illuminate\Database\Eloquent\Model;

class ClientSocialAccount extends Model
{
    protected $fillable = [
        'client_id',
        'provider', // obsoleto
        'platform',
        'account_name',
        'account_url',
        'username',
        'account_exists',
        'access_method',
        'access_status',
        'is_ready_to_publish',
        'access_verified_at',
        'access_verified_by',
        
        // Identificativi account specifici per piattaforma
        'business_manager_id',
        'business_center_id',
        'tiktok_account_id',
        'credential_location',
        
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
            'api_provider' => SocialApiProvider::class,
            'api_status' => SocialApiStatus::class,
            
            'access_verified_at' => 'datetime',
            'token_expires_at' => 'datetime',
            
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
        ];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
    
    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'access_verified_by');
    }

    public function isReadyToPublish(): bool
    {
        return $this->is_ready_to_publish 
            && $this->access_status === SocialAccessStatus::ReadyToPublish;
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
