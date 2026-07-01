<?php

namespace Tests\Feature\Api\V1\Integrations\N8n;

use App\Models\Client;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketComment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutgoingMessageStatusContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.n8n.token' => 'secret-testing-token']);
    }

    protected function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer secret-testing-token',
        ];
    }

    public function test_ticket_comment_sent_status()
    {
        $client = Client::factory()->create();
        $project = Project::factory()->create(['client_id' => $client->id]);
        $ticket = Ticket::factory()->create(['client_id' => $client->id, 'project_id' => $project->id, 'title' => 'Test Ticket']);
        
        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => \App\Models\User::factory()->create()->id,
            'body' => 'Test comment',
            'delivery_channel' => 'sody',
            'delivery_status' => 'pending',
        ]);

        $payload = [
            'status' => 'sent',
            'external_message_id' => 'msg-123',
        ];

        $this->postJson("/api/v1/integrations/n8n/chatbot/outgoing-messages/ticket_comment_{$comment->id}/status", $payload, $this->getHeaders())
            ->assertStatus(200);

        $this->assertEquals('sent', $comment->refresh()->delivery_status);
        $this->assertEquals('msg-123', $comment->external_message_id);
    }

    public function test_ticket_comment_failed_status()
    {
        $client = Client::factory()->create();
        $project = Project::factory()->create(['client_id' => $client->id]);
        $ticket = Ticket::factory()->create(['client_id' => $client->id, 'project_id' => $project->id, 'title' => 'Test Ticket']);
        
        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => \App\Models\User::factory()->create()->id,
            'body' => 'Test comment',
            'delivery_channel' => 'sody',
            'delivery_status' => 'processing',
        ]);

        $payload = [
            'status' => 'failed',
            'error' => 'Network timeout',
        ];

        $this->postJson("/api/v1/integrations/n8n/chatbot/outgoing-messages/ticket_comment_{$comment->id}/status", $payload, $this->getHeaders())
            ->assertStatus(200);

        $this->assertEquals('failed', $comment->refresh()->delivery_status);
        $this->assertEquals('Network timeout', $comment->delivery_error);
    }

    public function test_unsupported_message_id_returns_400()
    {
        $payload = [
            'status' => 'sent',
        ];

        $this->postJson("/api/v1/integrations/n8n/chatbot/outgoing-messages/invalid_id_123/status", $payload, $this->getHeaders())
            ->assertStatus(400);
    }

    public function test_non_existent_comment_returns_404()
    {
        $payload = [
            'status' => 'sent',
        ];

        $this->postJson("/api/v1/integrations/n8n/chatbot/outgoing-messages/ticket_comment_9999/status", $payload, $this->getHeaders())
            ->assertStatus(404);
    }

    public function test_wrong_delivery_channel_returns_400()
    {
        $client = Client::factory()->create();
        $project = Project::factory()->create(['client_id' => $client->id]);
        $ticket = Ticket::factory()->create(['client_id' => $client->id, 'project_id' => $project->id, 'title' => 'Test Ticket']);
        
        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => \App\Models\User::factory()->create()->id,
            'body' => 'Test comment',
            'delivery_channel' => 'email', // not sody
            'delivery_status' => 'pending',
        ]);

        $payload = [
            'status' => 'sent',
        ];

        $this->postJson("/api/v1/integrations/n8n/chatbot/outgoing-messages/ticket_comment_{$comment->id}/status", $payload, $this->getHeaders())
            ->assertStatus(400);
    }

    public function test_already_same_status_is_idempotent()
    {
        $client = Client::factory()->create();
        $project = Project::factory()->create(['client_id' => $client->id]);
        $ticket = Ticket::factory()->create(['client_id' => $client->id, 'project_id' => $project->id, 'title' => 'Test Ticket']);
        
        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => \App\Models\User::factory()->create()->id,
            'body' => 'Test comment',
            'delivery_channel' => 'sody',
            'delivery_status' => 'sent', // already sent
        ]);

        $payload = [
            'status' => 'sent',
        ];

        $this->postJson("/api/v1/integrations/n8n/chatbot/outgoing-messages/ticket_comment_{$comment->id}/status", $payload, $this->getHeaders())
            ->assertStatus(200); // Idempotente, nessuna eccezione
    }
}
