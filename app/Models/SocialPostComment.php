<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialPostComment extends Model
{
    protected $fillable = [
        'social_post_id',
        'social_post_version_id',
        'user_id',
        'client_name',
        'client_email',
        'body',
        'visibility',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'visibility' => \App\Enums\Social\SocialPostCommentVisibility::class,
            'type' => \App\Enums\Social\SocialPostCommentType::class,
        ];
    }

    public function post()
    {
        return $this->belongsTo(SocialPost::class, 'social_post_id');
    }

    public function version()
    {
        return $this->belongsTo(SocialPostVersion::class, 'social_post_version_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
