<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\{Client, User};
use App\Policies\Concerns\HandlesRoleAuthorization;

class ClientPolicy
{
    use HandlesRoleAuthorization;

    public function lookup(User $user): bool
    {
        return $user->isCommercial() || $this->viewAny($user);
    }

    public function quickCreate(User $user): bool
    {
        return $user->isCommercial() || $this->create($user);
    }

    public function selectForTicket(User $user, Client $client): bool
    {
        return $user->isCommercial()
            ? (int) $client->commercial_user_id === (int) $user->id
            : $this->view($user, $client);
    }

    public function viewAny(User $user): bool  
    { 
        return $user->role === UserRole::Administration || $user->role === UserRole::OperationsManager; 
    }
    
    public function view(User $user, Client $client): bool
    { 
        if ($user->isCommercial()) {
            return false;
        }

        if ($user->role === UserRole::Administration || $user->role === UserRole::OperationsManager) {
            return true;
        }
        
        return $user->projects()->where('client_id', $client->id)->exists();
    }
    
    public function create(User $user): bool   
    { 
        return $user->role === UserRole::Administration || $user->role === UserRole::OperationsManager;
    }
    
    public function update(User $user, Client $client): bool  
    { 
        return $user->role === UserRole::OperationsManager;
    }
    
    public function delete(User $user, Client $client): bool  
    { 
        return false; // Autorizzazione gestita dal metodo before()
    }
}
