<?php

namespace App\Policies;

use App\Models\{CalendarEvent, User};
use App\Policies\Concerns\HandlesRoleAuthorization;

class CalendarEventPolicy
{
    use HandlesRoleAuthorization;

    // Ignora le policy specifiche se l'utente ha privilegi globali
    public function before(User $user, string $ability, mixed $event = null): ?bool
    {
        // Se è un evento personale, blocchiamo il bypass dell'admin.
        if ($event instanceof CalendarEvent && $event->type === 'personal') {
            return null; 
        }

        if ($user->canManageSystem()) {
            return true;
        }

        return null;
    }

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
        // Solo il proprietario può eliminare il suo evento personale. Per il resto ci pensa before()
        if ($event->type === 'personal') {
            return $this->ownsPersonalEvent($user, $event);
        }
        return false; // Per gli altri eventi, l'autorizzazione di delete è gestita dal before() per l'admin.
    }

    private function ownsPersonalEvent(User $user, CalendarEvent $event): bool
    {
        return $event->type === 'personal'
            && (
                $event->created_by === $user->id
                || $event->assigned_to === $user->id
            );
    }

    private function canAccessEvent(User $user, CalendarEvent $event): bool
    {
        if ($event->type === 'personal') {
            return $this->ownsPersonalEvent($user, $event);
        }

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
