<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialPostVersion extends Model
{
    protected $fillable = [
        'social_post_id',
        'version_number',
        'caption',
        'image_path',
        'original_image_url',
        'prompt_used',
        'source',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'source' => \App\Enums\Social\SocialPostSource::class,
        ];
    }

    public function post()
    {
        return $this->belongsTo(SocialPost::class, 'social_post_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments()
    {
        return $this->hasMany(SocialPostComment::class);
    }
}
