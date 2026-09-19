<?php

namespace App\Policies;

use App\Models\ClientSocialAccount;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ClientSocialAccountPolicy
{

    public function viewAny(User $user): bool
    {
        return $user->canManageMarketing() || $user->isAdministration();
    }


    public function view(User $user, ClientSocialAccount $clientSocialAccount): bool
    {
        return $user->canManageMarketing() || $user->isAdministration();
    }


    public function create(User $user): bool
    {
        return $user->canManageMarketing();
    }


    public function update(User $user, ClientSocialAccount $clientSocialAccount): bool
    {
        return $user->canManageMarketing();
    }


    public function delete(User $user, ClientSocialAccount $clientSocialAccount): bool
    {
        return $user->isAdmin();
    }


    public function restore(User $user, ClientSocialAccount $clientSocialAccount): bool
    {
        return $user->isAdmin();
    }


    public function forceDelete(User $user, ClientSocialAccount $clientSocialAccount): bool
    {
        return $user->isAdmin();
    }
}
