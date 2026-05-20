<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class UserScopedShootScope implements Scope
{
    // Applica lo scope di visibilità globale agli shooting
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        // Nessuna restrizione per utenti con privilegi globali
        if (!$user || $user->canBypassProjectScope()) {
            return;
        }

        // Filtro assegnazione diretta per fotografi
        if ($user->isPhotographer()) {
            $builder->where('photographer_id', $user->id);
            return;
        }

        // Filtro di progetto per team interno (Marketing, Developer)
        $builder->where(function($q) use ($user) {
            $q->whereHas('project', function ($q2) use ($user) {
                $q2->whereIn('projects.id', $user->projects()->pluck('projects.id'));
            })->orWhereHas('marketingCampaign.client', function ($q2) use ($user) {
                $q2->whereHas('users', function ($q3) use ($user) {
                    $q3->where('user_id', $user->id);
                });
            });
        });
    }
}
