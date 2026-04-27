<?php

namespace App\Policies\Concerns;

use App\Enums\UserRole;
use App\Models\User;

trait HandlesRoleAuthorization
{
    /**
     * Admin bypassa tutte le Policy.
     * Returning true qui fa saltare il metodo specifico.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->canManageSystem()) {
            return true;
        }
        return null; // continua con il metodo specifico
    }
}
