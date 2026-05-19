<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\Social\AgencyConnectionStatus;

class AgencySocialConnection extends Model
{
    protected $guarded = [];

    protected $casts = [
        'status' => AgencyConnectionStatus::class,
        'scopes' => 'array',
        'token_expires_at' => 'datetime',
        'last_token_refresh_at' => 'datetime',
        'requires_reauth' => 'boolean',
        'connected_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'last_api_check_at' => 'datetime',
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
    ];

    public function assets()
    {
        return $this->hasMany(AgencySocialAsset::class);
    }

    public function connectedBy()
    {
        return $this->belongsTo(User::class, 'connected_by');
    }
}
