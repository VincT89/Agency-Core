<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\{Project, User};
use App\Policies\Concerns\HandlesRoleAuthorization;

class ProjectPolicy
{
    use HandlesRoleAuthorization;

    public function viewAny(User $user): bool  
    { 
        return $user->canManageSystem() || $user->role === UserRole::Administration || in_array($user->role, [
            UserRole::Developer, 
            UserRole::Marketing, 
            UserRole::Photographer, 
            UserRole::GraphicDesigner
        ], true);
    }
    
    public function view(User $user, Project $project): bool  
    { 
        if ($user->role === UserRole::Administration) {
            return true;
        }

        return $user->projects()->where('projects.id', $project->id)->exists();
    }
    
    public function create(User $user): bool   
    { 
        return $user->role === UserRole::Administration;
    }

    public function update(User $user, Project $project): bool
    {
        return $user->role === UserRole::Administration;
    }

    public function delete(User $user, Project $project): bool
    {
        return false; // Autorizzazione gestita dal metodo before()
    }
}
