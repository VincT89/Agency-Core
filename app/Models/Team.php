<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * ==========================================
 * AREA SEMANTICA: TEAM AZIENDALE
 * ==========================================
 * I Team in questa architettura rappresentano unità organizzative 
 * Globali o Corporate a livello HR (es. "Design Team", "Marketing Team").
 * NON sono limitati o legati a uno specifico Progetto.
 * 
 * ATTENZIONE: Se in futuro i verticali (es. Shooting/Marketing) 
 * richiederanno "crew" o "gruppi di lavoro" specifici per un progetto, 
 * NON utilizzare questo modello Team. Creare piuttosto strutture 
 * ad-hoc legate al perimetro operativo (es. ProjectCrew o simile).
 */
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