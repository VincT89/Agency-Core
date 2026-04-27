<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class UserScopedShootScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        // If no user is authenticated, or if the user is a system admin/administration, 
        // we don't apply any restrictions (they can see everything).
        if (!$user || $user->canBypassProjectScope()) {
            return;
        }

        // Se è fotografo, vede solo gli shooting a lui assegnati
        if ($user->isPhotographer()) {
            $builder->where('photographer_id', $user->id);
            return;
        }

        // Per Marketing e Developer, vedono gli shooting legati ai progetti di cui fanno parte
        $builder->whereHas('project', function ($q) use ($user) {
            $q->whereIn('projects.id', $user->projects()->pluck('projects.id'));
        });
    }
}
