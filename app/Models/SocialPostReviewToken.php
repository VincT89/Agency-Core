<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialPostReviewToken extends Model
{
    protected $fillable = [
        'social_post_id',
        'token',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function post()
    {
        return $this->belongsTo(SocialPost::class, 'social_post_id');
    }
}
