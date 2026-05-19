<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\Social\AgencyConnectionStatus;
use App\Enums\Social\SocialAssetType;
use App\Enums\Social\PublishingStatus;

class AgencySocialAsset extends Model
{
    protected $guarded = [];

    protected $casts = [
        'status' => AgencyConnectionStatus::class,
        'asset_type' => SocialAssetType::class,
        'publishing_status' => PublishingStatus::class,
        
        'capabilities' => 'array',
        'raw_payload' => 'array',
        'publishing_capabilities' => 'array',
        
        'page_token_last_validated_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_synced_at' => 'datetime',
        
        'is_assignable' => 'boolean',
        'is_active' => 'boolean',
        
        'page_access_token' => 'encrypted',
    ];

    public function connection()
    {
        return $this->belongsTo(AgencySocialConnection::class, 'agency_social_connection_id');
    }

    public function parentAsset()
    {
        return $this->belongsTo(AgencySocialAsset::class, 'parent_asset_id');
    }
    
    public function childAssets()
    {
        return $this->hasMany(AgencySocialAsset::class, 'parent_asset_id');
    }

    public function clientSocialAccounts()
    {
        return $this->hasMany(ClientSocialAccount::class, 'agency_social_asset_id');
    }
}
