<?php

namespace App\Policies;

use App\Models\{HostingService, User};
use App\Policies\Concerns\HandlesRoleAuthorization;

class HostingServicePolicy
{
    use HandlesRoleAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, HostingService $hostingService): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, HostingService $hostingService): bool
    {
        return true;
    }

    public function delete(User $user, HostingService $hostingService): bool
    {
        return true;
    }

    public function viewPassword(User $user, HostingService $hostingService): bool
    {
        // Al momento ogni operatore autenticato può vedere le password, 
        // in futuro questa logica può essere ristretta per ruoli specifici.
        return true;
    }
}
