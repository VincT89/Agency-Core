<?php

namespace App\Domain\Core\Actions;

use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use App\Notifications\TicketUnassignedNotification;
use App\Services\Tickets\TicketNotificationRecipientResolver;

class CreateTicketAction
{
    public function __construct(
        private TicketNotificationRecipientResolver $resolver
    ) {
    }

    public function execute(array $data): Ticket
    {
        return DB::transaction(function () use ($data) {
            $data['created_by'] = auth()->id();
            $data['opened_at'] = $data['opened_at'] ?? now();

            if (($data['status'] ?? null) === 'closed') {
                $data['closed_at'] = now();
            } else {
                $data['closed_at'] = null;
            }

            $ticket = Ticket::create($data);

            if (!empty($data['assigned_to'])) {
                event(new \App\Domain\Core\Events\TicketAssigned($ticket));
            } else {
                foreach ($this->resolver->admins() as $admin) {
                    $admin->notify(new TicketUnassignedNotification($ticket));
                }
            }

            return $ticket;
        });
    }
}
