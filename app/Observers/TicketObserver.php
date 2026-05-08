<?php

namespace App\Observers;

use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketAssignedNotification;
use App\Services\AuditLogService;

class TicketObserver
{
    public function saved(Ticket $ticket): void
    {
        if ($ticket->wasChanged([
            'title', 
            'description', 
            'type', 
            'code', 
            'status', 
            'priority', 
            'assigned_to', 
            'client_id',
            'due_date',
            'opened_at',
            'closed_at'
        ])) {
            \App\Jobs\Chatbot\SyncChatbotClientDataJob::dispatch($ticket->client_id)
                ->delay(now()->addSeconds(10))
                ->onQueue('chatbot');
        }
    }

    public function __construct(protected AuditLogService $auditLog) {}

    public function created(Ticket $ticket): void
    {
        if (empty($ticket->code)) {
            $year = $ticket->created_at ? $ticket->created_at->format('Y') : date('Y');
            $code = sprintf('TCK-%s-%06d', $year, $ticket->id);
            $ticket->updateQuietly(['code' => $code]);
        }

        $this->auditLog->log('created', $ticket, null, $ticket->getAttributes());
    }

    public function updated(Ticket $ticket): void
    {
        $old = array_intersect_key($ticket->getOriginal(), $ticket->getDirty());
        $new = $ticket->getDirty();

        // Log separato se lo status è cambiato
        $action = isset($new['status']) ? 'status_changed' : 'updated';
        $this->auditLog->log($action, $ticket, $old, $new);
    }

    public function deleted(Ticket $ticket): void
    {
        $this->auditLog->log('deleted', $ticket, $ticket->getOriginal(), null);
        
        \App\Jobs\Chatbot\SyncChatbotClientDataJob::dispatch($ticket->client_id)
            ->delay(now()->addSeconds(10))
            ->onQueue('chatbot');
    }
}
