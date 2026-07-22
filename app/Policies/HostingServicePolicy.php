<?php

namespace App\Policies;

use App\Models\{HostingService, User};
use App\Policies\Concerns\HandlesRoleAuthorization;

class HostingServicePolicy
{
    use HandlesRoleAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isDeveloper() || $user->canAccessFinance() || $user->isOperationsManager();
    }

    public function view(User $user, HostingService $hostingService): bool
    {
        return $user->isAdmin() || $user->isDeveloper() || $user->canAccessFinance() || $user->isOperationsManager();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isDeveloper() || $user->canAccessFinance() || $user->isOperationsManager();
    }

    public function update(User $user, HostingService $hostingService): bool
    {
        return $user->isAdmin() || $user->isDeveloper() || $user->canAccessFinance() || $user->isOperationsManager();
    }

    public function delete(User $user, HostingService $hostingService): bool
    {
        return $user->isAdmin();
    }

    public function viewPassword(User $user, HostingService $hostingService): bool
    {
        return $this->manageCredentials($user, $hostingService);
    }

    public function manageCredentials(User $user, ?HostingService $hostingService = null): bool
    {
        return $user->isAdmin() || $user->isDeveloper();
    }
}
