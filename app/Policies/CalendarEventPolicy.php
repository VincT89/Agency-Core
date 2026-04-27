<?php

namespace App\Policies;

use App\Models\{CalendarEvent, User};
use App\Policies\Concerns\HandlesRoleAuthorization;

class CalendarEventPolicy
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

    public function view(User $user, CalendarEvent $event): bool
    {
        return $this->canAccessEvent($user, $event);
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

    public function update(User $user, CalendarEvent $event): bool
    {
        return $this->canAccessEvent($user, $event);
    }

    public function delete(User $user, CalendarEvent $event): bool
    {
        return false; // Handled by before()
    }

    private function canAccessEvent(User $user, CalendarEvent $event): bool
    {
        if ($event->project_id) {
            // Regola Suprema: Il perimetro del progetto vince sempre.
            return $user->projects()->where('projects.id', $event->project_id)->exists();
        }

        // Se l'evento NON ha progetto (Personal Event), fallback su Ownership
        if ($event->assigned_to === $user->id || $event->created_by === $user->id) {
            return true;
        }
        
        return false;
    }
}
