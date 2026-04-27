<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use App\Models\Project;

class ProjectSupremacyScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        // Fail-Closed policy: se nessun utente auth e nessuno bypassa, droppa la query a 0 risultati
        if (!Auth::check()) {
            $builder->whereRaw('1 = 0');
            return;
        }

        $user = Auth::user();

        // Bypass per chi ha visibilità globale (Admin, Administration)
        if ($user->canBypassProjectScope()) {
            return;
        }

        $projectColumn = $model instanceof Project ? $model->getTable() . '.id' : $model->getTable() . '.project_id';

        $builder->where(function ($query) use ($user, $model, $projectColumn) {
            
            // 1. Project Supremacy (Tutti i model protetti da questo scope)
            $query->whereIn($projectColumn, function ($sub) use ($user) {
                $sub->select('project_id')
                    ->from('project_user')
                    ->where('user_id', $user->id);
            });

            // 2. Fallbacks/Eccezioni previste per entities miste
            if ($model instanceof \App\Models\CalendarEvent) {
                $query->orWhere(function ($q) use ($user) {
                    $q->whereNull('project_id')
                      ->where('assigned_to', $user->id);
                });
            }
            
            // Note: per Task o Ticket l'assegnazione è sempre dentro un project_id, 
            // la UI vincola. Se si volesse ammettere Task standalone, si sbloccherebbe come CalendarEvent.
            // (La rigorosità della Project Supremacy dice che non ci sono eccezioni per Task e Ticket).
        });
    }
}
