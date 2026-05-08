<?php

namespace App\Policies;

use App\Models\{HostingService, User};
use App\Policies\Concerns\HandlesRoleAuthorization;

class HostingServicePolicy
{
    use HandlesRoleAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isDeveloper();
    }

    public function view(User $user, HostingService $hostingService): bool
    {
        return $user->isAdmin() || $user->isDeveloper();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isDeveloper();
    }

    public function update(User $user, HostingService $hostingService): bool
    {
        return $user->isAdmin() || $user->isDeveloper();
    }

    public function delete(User $user, HostingService $hostingService): bool
    {
        return $user->isAdmin();
    }

    public function viewPassword(User $user, HostingService $hostingService): bool
    {
        return $user->isAdmin() || $user->isDeveloper();
    }
}
