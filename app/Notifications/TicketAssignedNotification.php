<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TicketAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(public Ticket $ticket)
    {
        //
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'ticket_assigned',
            'title'   => 'Nuovo ticket assegnato',
            'message' => 'Ti è stato assegnato il ticket: ' . $this->ticket->title,
            'url'     => route('tickets.show', $this->ticket),
            'ticket_id' => $this->ticket->id,
        ];
    }
}
