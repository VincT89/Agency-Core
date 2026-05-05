<?php

namespace App\Domain\Tickets\Actions;

use App\Models\Project;
use App\Models\Ticket;

class CreateTicketFromN8n
{
    public function execute(array $data): array
    {
        $source = $data['source'];
        $externalId = $data['n8n_execution_id'];

        $existing = Ticket::withoutGlobalScope(\App\Models\Scopes\ProjectSupremacyScope::class)
            ->where('source', $source)
            ->where('external_id', $externalId)
            ->first();

        if ($existing) {
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
        ]);

        return [
            'ticket' => $ticket,
            'created' => true,
        ];
    }
}
