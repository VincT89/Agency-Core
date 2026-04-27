<?php

namespace App\Policies;

use App\Models\{Task, User};
use App\Policies\Concerns\HandlesRoleAuthorization;

class TaskPolicy
{
    use HandlesRoleAuthorization;

    public function viewAny(User $user): bool  
    { 
        return $user->canManageSystem() || in_array($user->role, [
            \App\Enums\UserRole::Developer, 
            \App\Enums\UserRole::Marketing, 
            \App\Enums\UserRole::Photographer, 
            \App\Enums\UserRole::GraphicDesigner
        ], true); 
    }

    public function view(User $user, Task $task): bool
    {
        return $this->canAccessTask($user, $task);
    }

    public function create(User $user): bool   
    { 
        return $user->canManageSystem() || in_array($user->role, [
            \App\Enums\UserRole::Developer, 
            \App\Enums\UserRole::Marketing, 
            \App\Enums\UserRole::Photographer, 
            \App\Enums\UserRole::GraphicDesigner
        ], true); 
    }

    public function update(User $user, Task $task): bool
    {
        return $this->canAccessTask($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return false; // Handled by before()
    }

    private function canAccessTask(User $user, Task $task): bool
    {
        // Regola Suprema: Appartenenza al progetto collegato
        if ($task->project_id && $user->projects()->where('projects.id', $task->project_id)->exists()) {
            return true;
        }

        return false;
    }
}
