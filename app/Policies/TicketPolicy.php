<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\{Ticket, User};
use App\Policies\Concerns\HandlesRoleAuthorization;

class TicketPolicy
{
    use HandlesRoleAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->canManageSystem() || in_array($user->role, [
            \App\Enums\UserRole::Developer, 
            \App\Enums\UserRole::GraphicDesigner,
            \App\Enums\UserRole::OperationsManager,
            UserRole::Commercial,
        ], true);
    }

    public function view(User $user, Ticket $ticket): bool
    {
        if (!$this->viewAny($user)) {
            return false;
        }
        return $this->canAccessTicket($user, $ticket);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Ticket $ticket): bool
    {
        if ($user->isCommercial()) {
            return false;
        }
        return $this->canAccessTicket($user, $ticket);
    }

    public function comment(User $user, Ticket $ticket): bool
    {
        return $user->isCommercial()
            ? $this->view($user, $ticket)
            : $this->update($user, $ticket);
    }

    public function addAttachment(User $user, Ticket $ticket): bool
    {
        return $this->comment($user, $ticket);
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return false; // Autorizzazione gestita dal metodo before()
    }

    private function canAccessTicket(User $user, Ticket $ticket): bool
    {
        if ($user->isCommercial()) {
            return (int) $ticket->created_by === (int) $user->id;
        }

        if ($user->canBypassProjectScope()) {
            return true;
        }

        if (!$ticket->project_id && (int) $ticket->assigned_to === (int) $user->id) {
            return true;
        }

        // Limita la visibilità al perimetro del progetto
        if ($ticket->project_id && $user->projects()->where('projects.id', $ticket->project_id)->exists()) {
            return true;
        }

        return false;
    }
}
