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
            unset($data['assignment_department']);
            if (auth()->user()?->isCommercial()) {
                $data = \Illuminate\Support\Arr::only($data, [
                    'client_id', 'project_id', 'title', 'description', 'type', 'priority', 'requested_department', 'requested_services',
                ]);
                $data['status'] = 'open';
                $data['assigned_to'] = null;
            }
            $data['created_by'] = auth()->id();
            $data['opened_at'] = $data['opened_at'] ?? now();

            if (($data['status'] ?? null) === 'closed') {
                $data['closed_at'] = now();
            } else {
                $data['closed_at'] = null;
            }

            $services = $data['requested_services'] ?? [];
            unset($data['requested_services']);
            $ticket = Ticket::create($data);
            if ($ticket->type === 'quote') {
                foreach (array_values($services) as $index => $service) {
                    $ticket->requestedServices()->create($service + ['sort_order' => $index]);
                }
            }

            if (!empty($data['assigned_to'])) {
                event(new \App\Domain\Core\Events\TicketAssigned($ticket));
            } else {
                foreach ($this->resolver->intakeRecipients($ticket) as $admin) {
                    if ($admin->id !== auth()->id()) {
                        $admin->notify(new TicketUnassignedNotification($ticket));
                    }
                }
            }

            return $ticket;
        });
    }
}
