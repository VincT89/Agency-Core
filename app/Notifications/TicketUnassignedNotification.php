<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TicketUnassignedNotification extends Notification
{
    use Queueable;

    public function __construct(public Ticket $ticket)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ticket_unassigned',
            'title' => 'Nuovo ticket non assegnato',
            'message' => 'È stato creato un nuovo ticket non assegnato: ' . $this->ticket->title,
            'url' => route('tickets.show', $this->ticket),
            'ticket_id' => $this->ticket->id,
            'ticket_code' => $this->ticket->code,
            'priority' => $this->ticket->priority,
            'due_date' => $this->ticket->due_date?->toDateString(),
        ];
    }
}
