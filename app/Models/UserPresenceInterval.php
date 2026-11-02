<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPresenceInterval extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['started_at' => 'integer', 'ended_at' => 'integer'];
    }
}
