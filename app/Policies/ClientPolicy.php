<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\{Client, User};
use App\Policies\Concerns\HandlesRoleAuthorization;

class ClientPolicy
{
    use HandlesRoleAuthorization;

    public function viewAny(User $user): bool  
    { 
        return $user->role === UserRole::Administration || $user->role === UserRole::OperationsManager; 
    }
    
    public function view(User $user, Client $client): bool
    { 
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
