<?php

namespace App\Domain\Tickets\Actions;

use App\Models\Project;
use App\Models\Ticket;
use App\Notifications\TicketUnassignedNotification;
use App\Services\Tickets\TicketNotificationRecipientResolver;

class CreateTicketFromN8n
{
    public function __construct(
        private TicketNotificationRecipientResolver $resolver
    ) {
    }

    public function execute(array $data): array
    {
        $source = $data['source'];
        $externalId = $data['n8n_execution_id'];

        $existing = Ticket::withoutGlobalScope(\App\Models\Scopes\ProjectSupremacyScope::class)
            ->where('source', $source)
            ->where('external_id', $externalId)
            ->first();

        if ($existing) {
            $this->ensureChatbotIntegration($existing);

            return [
                'ticket' => $existing,
                'created' => false,
            ];
        }

        $project = null;

        if (! empty($data['project_id'])) {
            $project = Project::withoutGlobalScope(\App\Models\Scopes\ProjectSupremacyScope::class)
                ->findOrFail($data['project_id']);
            $clientId = $project->client_id;
        } else {
            $clientId = $data['client_id'];
        }

        $description = $data['description']
            ?? data_get($data, 'context.original_message')
            ?? 'Ticket creato automaticamente da n8n.';

        try {
            $ticket = Ticket::create([
                'client_id' => $clientId,
                'project_id' => $project?->id,
                'created_by' => null,

                'title' => $data['title'] ?? 'Ticket WhatsApp',
                'description' => $description,
                'priority' => $data['priority'] ?? 'medium',
                'status' => 'open',

                'source' => $source,
                'external_id' => $externalId,
                'context' => $data['context'] ?? [],
                'received_at' => now(),
                'opened_at' => now(),
            ]);

            $this->ensureChatbotIntegration($ticket);

            foreach ($this->resolver->admins() as $admin) {
                $admin->notify(new TicketUnassignedNotification($ticket));
            }

            return [
                'ticket' => $ticket,
                'created' => true,
            ];
        } catch (\Illuminate\Database\QueryException $e) {
            // Check for unique constraint violation (source + external_id)
            if ($e->getCode() == 23000) {
                $existing = Ticket::withoutGlobalScope(\App\Models\Scopes\ProjectSupremacyScope::class)
                    ->where('source', $source)
                    ->where('external_id', $externalId)
                    ->first();

                if ($existing) {
                    $this->ensureChatbotIntegration($existing);
                    
                    return [
                        'ticket' => $existing,
                        'created' => false,
                    ];
                }
            }
            throw $e;
        }
    }
    
    private function ensureChatbotIntegration(Ticket $ticket): void
    {
        \App\Models\ChatbotClientSession::updateOrCreate([
            'client_id' => $ticket->client_id,
            'session_type' => 'ticket',
            'session_id' => $ticket->id,
        ]);

        app(\App\Domain\Chatbot\Actions\SyncChatbotTicketsAction::class)->syncOne($ticket);
    }
}
