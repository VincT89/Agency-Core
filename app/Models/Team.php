<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// Modello Team aziendale, unità HR globale non legata ai singoli progetti
#[Fillable([
    'name',
    'description',
    'is_active',
])]
class Team extends Model
{
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'assignment_status', 'joined_at'])
            ->withTimestamps();
    }
    
}