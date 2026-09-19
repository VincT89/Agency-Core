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

        // Progetti assegnati e campagne visibili, oltre agli incarichi personali.
        $builder->where(function($q) use ($user) {
            $q->whereHas('project', function ($q2) use ($user) {
                $q2->whereIn('projects.id', $user->projects()->pluck('projects.id'));
            })->orWhere(function ($campaignShoots) use ($user) {
                $campaignShoots->whereNull('project_id')
                    ->whereHas('marketingCampaign', fn ($campaign) => $campaign->visibleTo($user));
            });

            if ($user->isPhotographer()) {
                $q->orWhere('photographer_id', $user->id);
            }
        });
    }
}
