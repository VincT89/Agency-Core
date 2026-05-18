<?php

namespace App\Domain\Chatbot\Actions;

use App\Models\Client;
use App\Models\Chatbot\ChatbotClient;
use App\Models\Chatbot\ChatbotTicket;
use App\Domain\Chatbot\Support\ChatbotLabelMapper;

class SyncChatbotTicketsAction
{
    private const MAX_TICKETS_PER_CLIENT = 50;

    public function execute(Client $client, ChatbotClient $chatbotClient): void
    {
        // Ultimi ticket
        $latestTickets = $client->tickets()
            ->withoutGlobalScopes()
            ->with('assignee')
            ->orderByDesc('updated_at')
            ->limit(self::MAX_TICKETS_PER_CLIENT)
            ->get();

        foreach ($latestTickets as $ticket) {
            ChatbotTicket::updateOrCreate(
                [
                    'ticket_id' => $ticket->id,
                ],
                [
                    'chatbot_client_id' => $chatbotClient->id,
                    'client_id' => $client->id,
                    'title' => $ticket->title,
                    'description' => $ticket->description,
                    'type' => ChatbotLabelMapper::ticketType($ticket->type),
                    'code' => $ticket->code,
                    'status' => ChatbotLabelMapper::status($ticket->status),
                    'priority' => ChatbotLabelMapper::priority($ticket->priority),
                    'assigned_to_user_id' => $ticket->assigned_to,
                    'assigned_to_name' => $ticket->assignee->name ?? null,
                    'due_date' => $ticket->due_date,
                    'opened_at' => $ticket->opened_at,
                    'closed_at' => $ticket->closed_at,
                    'source_created_at' => $ticket->created_at,
                    'source_updated_at' => $ticket->updated_at,
                    'synced_at' => now(),
                ]
            );
        }

        // Retention
        
        // Logica di retention: manteniamo sempre e solo gli ultimi N ticket.
        // I record più vecchi (anche se modificati in precedenza e finiti nel read model) 
        // vengono eliminati dalla projection per non appesantire n8n.
        $validTicketIds = $latestTickets->pluck('id');

        ChatbotTicket::where('chatbot_client_id', $chatbotClient->id)
            ->whereNotIn('ticket_id', $validTicketIds)
            ->delete();
    }

    public function syncOne(\App\Models\Ticket $ticket): void
    {
        $ticket->loadMissing('client', 'assignee');

        $chatbotClient = app(\App\Domain\Chatbot\Actions\SyncChatbotClientAction::class)
            ->execute($ticket->client);

        ChatbotTicket::updateOrCreate(
            ['ticket_id' => $ticket->id],
            [
                'chatbot_client_id' => $chatbotClient->id,
                'client_id' => $ticket->client_id,
                'title' => $ticket->title,
                'description' => $ticket->description,
                'type' => ChatbotLabelMapper::ticketType($ticket->type),
                'code' => $ticket->code,
                'status' => ChatbotLabelMapper::status($ticket->status),
                'priority' => ChatbotLabelMapper::priority($ticket->priority),
                'assigned_to_user_id' => $ticket->assigned_to,
                'assigned_to_name' => $ticket->assignee?->name,
                'due_date' => $ticket->due_date,
                'opened_at' => $ticket->opened_at,
                'closed_at' => $ticket->closed_at,
                'source_created_at' => $ticket->created_at,
                'source_updated_at' => $ticket->updated_at,
                'synced_at' => now(),
            ]
        );
    }
}
