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
        return false; // Autorizzazione gestita dal metodo before()
    }

    private function canAccessEvent(User $user, CalendarEvent $event): bool
    {
        if ($event->project_id) {
            // Limita la visibilità al perimetro del progetto
            return $user->projects()->where('projects.id', $event->project_id)->exists();
        }

        // Fallback sul creatore/assegnatario per eventi non legati a progetti
        if ($event->assigned_to === $user->id || $event->created_by === $user->id) {
            return true;
        }
        
        return false;
    }
}
