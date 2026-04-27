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
            \App\Enums\UserRole::Marketing, 
            \App\Enums\UserRole::Photographer,
            \App\Enums\UserRole::GraphicDesigner
        ], true);
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $this->canAccessTicket($user, $ticket);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $this->canAccessTicket($user, $ticket);
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return false; // Handled by before()
    }

    private function canAccessTicket(User $user, Ticket $ticket): bool
    {
        // Regola Suprema: Appartenenza al progetto collegato
        if ($ticket->project_id && $user->projects()->where('projects.id', $ticket->project_id)->exists()) {
            return true;
        }

        return false;
    }
}
