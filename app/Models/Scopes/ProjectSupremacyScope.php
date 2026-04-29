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
        // Blocca la query se l'utente non è autenticato (Fail-Closed)
        if (!Auth::check()) {
            $builder->whereRaw('1 = 0');
            return;
        }

        $user = Auth::user();

        // Ignora il filtro per amministratori con visibilità globale
        if ($user->canBypassProjectScope()) {
            return;
        }

        $projectColumn = $model instanceof Project ? $model->getTable() . '.id' : $model->getTable() . '.project_id';

        $builder->where(function ($query) use ($user, $model, $projectColumn) {
            
            // Applica il filtro di appartenenza al progetto
            $query->whereIn($projectColumn, function ($sub) use ($user) {
                $sub->select('project_id')
                    ->from('project_user')
                    ->where('user_id', $user->id);
            });

            // Gestisce le eccezioni per entità non strettamente legate al progetto
            if ($model instanceof \App\Models\CalendarEvent) {
                $query->orWhere(function ($q) use ($user) {
                    $q->whereNull('project_id')
                      ->where('assigned_to', $user->id);
                });
            }
            
            // Task e Ticket restano volutamente bloccati al contesto del progetto
        });
    }
}
