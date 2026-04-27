<?php

namespace App\Domain\Core\Listeners;

use App\Domain\Core\Events\TicketAssigned;

class SendTicketAssignedNotification
{
    public function handle(TicketAssigned $event)
    {
        $ticket = $event->ticket;

        if ($ticket->assigned_to && $ticket->assigned_to !== $ticket->created_by && $ticket->assignee) {
             $ticket->assignee->notify(new \App\Notifications\TicketAssignedNotification($ticket));
        }
    }
}
