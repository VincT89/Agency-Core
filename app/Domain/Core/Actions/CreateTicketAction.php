<?php

namespace App\Domain\Core\Actions;

use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

class CreateTicketAction
{
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
            }

            return $ticket;
        });
    }
}
