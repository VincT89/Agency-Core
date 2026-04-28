<?php

namespace App\Models;

use App\Enums\Social\ClientSocialAccessStatus;
use App\Enums\Social\MetaApiStatus;
use Illuminate\Database\Eloquent\Model;

class ClientSocialAccount extends Model
{
    protected $fillable = [
        'client_id',
        'provider',
        'facebook_page_url',
        'instagram_profile_url',
        'meta_business_manager_id',
        'has_agency_access',
        'access_status',
        'facebook_page_id',
        'instagram_business_account_id',
        'access_token',
        'token_expires_at',
        'api_status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'has_agency_access' => 'boolean',
            'access_status' => ClientSocialAccessStatus::class,
            'api_status' => MetaApiStatus::class,
            'access_token' => 'encrypted',
            'token_expires_at' => 'datetime',
        ];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
