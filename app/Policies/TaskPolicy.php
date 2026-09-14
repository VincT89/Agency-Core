<?php

namespace App\Policies;

use App\Models\{Task, User};
use App\Policies\Concerns\HandlesRoleAuthorization;

class TaskPolicy
{
    use HandlesRoleAuthorization;

    public function viewAny(User $user): bool  
    { 
        if ($user->isCommercial() || $user->isAdministration()) {
            return true;
        }
        return $user->canManageSystem() || in_array($user->role, [
            \App\Enums\UserRole::Developer, 
            \App\Enums\UserRole::Marketing, 
            \App\Enums\UserRole::Photographer, 
            \App\Enums\UserRole::GraphicDesigner,
            \App\Enums\UserRole::OperationsManager,
        ], true); 
    }

    public function view(User $user, Task $task): bool
    {
        if ($user->isCommercial()) {
            return (int) $task->assigned_to === (int) $user->id
                || ($task->exists && Task::withoutGlobalScopes()->forCommercial($user)->whereKey($task->id)->exists());
        }
        return $this->canAccessTask($user, $task);
    }

    public function create(User $user): bool   
    { 
        return $user->canManageSystem() || in_array($user->role, [
            \App\Enums\UserRole::Developer, 
            \App\Enums\UserRole::Marketing, 
            \App\Enums\UserRole::Photographer, 
            \App\Enums\UserRole::GraphicDesigner,
            \App\Enums\UserRole::OperationsManager,
        ], true); 
    }

    public function update(User $user, Task $task): bool
    {
        if ($user->isCommercial()) {
            return false;
        }
        return $this->canAccessTask($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return false; // Autorizzazione gestita dal metodo before()
    }

    private function canAccessTask(User $user, Task $task): bool
    {
        if ($user->canBypassProjectScope()) {
            return true;
        }

        // Limita la visibilità al perimetro del progetto o ai task liberi assegnati direttamente
        if (!$task->project_id && $task->assigned_to === $user->id) {
            return true;
        }

        if ($task->project_id && $user->projects()->where('projects.id', $task->project_id)->exists()) {
            return true;
        }

        return false;
    }
}
