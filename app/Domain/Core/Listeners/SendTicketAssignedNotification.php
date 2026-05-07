<?php

namespace App\Domain\Core\Listeners;

use App\Domain\Core\Events\TicketAssigned;
use App\Notifications\TicketAssignedNotification;
use App\Services\Tickets\TicketNotificationRecipientResolver;

class SendTicketAssignedNotification
{
    public function __construct(
        private TicketNotificationRecipientResolver $resolver
    ) {
    }

    public function handle(TicketAssigned $event)
    {
        $ticket = $event->ticket;
        $ticket->load('assignee');

        $recipients = $this->resolver->recipientsFor($ticket);

        foreach ($recipients as $recipient) {
            $recipient->notify(new TicketAssignedNotification($ticket));
        }
    }
}
