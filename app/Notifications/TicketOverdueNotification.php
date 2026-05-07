<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TicketOverdueNotification extends Notification
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
            'type' => 'ticket_overdue',
            'title' => 'Ticket scaduto',
            'message' => 'Il ticket è scaduto: ' . $this->ticket->title,
            'url' => route('tickets.show', $this->ticket),
            'ticket_id' => $this->ticket->id,
            'ticket_code' => $this->ticket->code,
            'priority' => $this->ticket->priority,
            'due_date' => $this->ticket->due_date?->toDateString(),
        ];
    }
}
