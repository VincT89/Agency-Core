<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\{Project, User};
use App\Policies\Concerns\HandlesRoleAuthorization;

class ProjectPolicy
{
    use HandlesRoleAuthorization { before as private roleBefore; }

    public function before(User $user, string $ability, mixed $subject = null): ?bool
    {
        if ($ability === 'delete' && $subject instanceof Project && $subject->isInvoiceContainer()) {
            return false;
        }

        return $this->roleBefore($user, $ability);
    }

    public function viewAny(User $user): bool  
    {
        return $user->canBypassProjectScope() || in_array($user->role, [
            UserRole::Developer, 
            UserRole::Marketing, 
            UserRole::Photographer, 
            UserRole::GraphicDesigner
        ], true);
    }
    
    public function view(User $user, Project $project): bool  
    { 
        if ($user->canBypassProjectScope()) {
            return true;
        }

        return $user->projects()->where('projects.id', $project->id)->exists();
    }
    
    public function create(User $user): bool   
    { 
        return $user->role === UserRole::Administration || $user->role === UserRole::OperationsManager;
    }

    public function update(User $user, Project $project): bool
    {
        return $user->role === UserRole::Administration || $user->role === UserRole::OperationsManager;
    }

    public function delete(User $user, Project $project): bool
    {
        return false; // Autorizzazione gestita dal metodo before()
    }
}
