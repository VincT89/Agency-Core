<?php

namespace App\Services\Tickets;

use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Collection;

class TicketNotificationRecipientResolver
{
    public function admins(): Collection
    {
        return User::query()
            ->where('role', UserRole::Admin->value)
            ->where('status', 'active')
            ->get();
    }

    public function recipientsFor(Ticket $ticket): Collection
    {
        $recipients = $this->admins();

        if ($ticket->assignee && $ticket->assignee->status === 'active') {
            $recipients->push($ticket->assignee);
        }

        return $recipients->unique('id')->values();
    }
}
