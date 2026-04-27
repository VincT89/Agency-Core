<?php

namespace App\Helpers;

use App\Models\User;
use App\Models\Shooting\Shoot;

class ShootingRouteResolver
{
    /**
     * Risolve la route della pagina di dettaglio di uno shooting 
     * in base al ruolo dell'utente che la richiede.
     * 
     * Priorità: Admin > Social > Photographer
     */
    public static function showRouteFor(User $user, Shoot $shoot): string
    {
        if ($user->canManageSystem()) {
            return route('admin.shooting.show', $shoot);
        }

        if ($user->isPhotographer()) {
            return route('photography.shooting.show', $shoot);
        }

        return route('social.shooting.show', $shoot);
    }

    /**
     * Risolve la route della pagina index in base al ruolo dell'utente.
     * 
     * Priorità: Admin > Social > Photographer
     */
    public static function indexRouteFor(User $user): string
    {
        if ($user->canManageSystem()) {
            return route('admin.shooting.index');
        }

        if ($user->isPhotographer()) {
            return route('photography.shooting.index');
        }

        return route('social.shooting.index');
    }
}
