<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientReviewToken extends Model
{
    protected $fillable = [
        'reviewable_id',
        'reviewable_type',
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

    public function reviewable()
    {
        return $this->morphTo();
    }
}
