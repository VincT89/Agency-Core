<?php

namespace Tests\Feature\Api\V1\Integrations\N8n;

use App\Models\Client;
use App\Models\Project;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateTicketFromN8nTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.n8n.token' => 'test-token']);
    }

    public function test_creates_ticket_with_project_id()
    {
        $client = Client::factory()->create();
        $project = Project::factory()->create(['client_id' => $client->id]);

        $response = $this->postJson('/api/v1/integrations/n8n/tickets', [
            'project_id' => $project->id,
            'title' => 'Test Ticket',
            'description' => 'Test Desc',
            'priority' => 'high',
            'source' => 'whatsapp',
            'n8n_execution_id' => 'exec-123',
        ], [
            'Authorization' => 'Bearer test-token'
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.created', true)
                 ->assertJsonPath('data.client_id', $client->id)
                 ->assertJsonPath('data.project_id', $project->id);

        $this->assertDatabaseHas('tickets', [
            'project_id' => $project->id,
            'client_id' => $client->id,
            'source' => 'whatsapp',
            'external_id' => 'exec-123',
            'created_by' => null,
            'status' => 'open'
        ]);
    }

    public function test_does_not_duplicate_ticket()
    {
        $client = Client::factory()->create();

        // First request
        $this->postJson('/api/v1/integrations/n8n/tickets', [
            'client_id' => $client->id,
            'source' => 'whatsapp',
            'n8n_execution_id' => 'exec-unique',
            'title' => 'First Request'
        ], [
            'Authorization' => 'Bearer test-token'
        ])->assertStatus(201);

        // Second request with same source + execution id
        $response = $this->postJson('/api/v1/integrations/n8n/tickets', [
            'client_id' => $client->id,
            'source' => 'whatsapp',
            'n8n_execution_id' => 'exec-unique',
            'title' => 'Second Request'
        ], [
            'Authorization' => 'Bearer test-token'
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('data.created', false);

        $this->assertDatabaseCount('tickets', 1);
        $this->assertDatabaseHas('tickets', [
            'title' => 'First Request' // Make sure it didn't overwrite
        ]);
    }

    public function test_fails_if_no_client_id_and_no_project_id()
    {
        $response = $this->postJson('/api/v1/integrations/n8n/tickets', [
            'source' => 'whatsapp',
            'n8n_execution_id' => 'exec-fail',
        ], [
            'Authorization' => 'Bearer test-token'
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['client_id', 'project_id']);
    }

    public function test_fails_with_invalid_token()
    {
        $client = Client::factory()->create();

        $response = $this->postJson('/api/v1/integrations/n8n/tickets', [
            'client_id' => $client->id,
            'source' => 'whatsapp',
            'n8n_execution_id' => 'exec-123',
        ], [
            'Authorization' => 'Bearer wrong-token'
        ]);

        $response->assertStatus(403);
    }

    public function test_fails_if_client_id_and_project_id_do_not_match()
    {
        $client1 = Client::factory()->create();
        $client2 = Client::factory()->create();
        $project = Project::factory()->create(['client_id' => $client1->id]);

        $response = $this->postJson('/api/v1/integrations/n8n/tickets', [
            'client_id' => $client2->id, // Mismatch!
            'project_id' => $project->id,
            'source' => 'whatsapp',
            'n8n_execution_id' => 'exec-mismatch',
        ], [
            'Authorization' => 'Bearer test-token'
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['client_id']);
    }

    public function test_creates_ticket_with_client_id_only()
    {
        $client = Client::factory()->create();

        $response = $this->postJson('/api/v1/integrations/n8n/tickets', [
            'client_id' => $client->id,
            'title' => 'Client Only Ticket',
            'source' => 'whatsapp',
            'n8n_execution_id' => 'exec-client-only',
        ], [
            'Authorization' => 'Bearer test-token'
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.client_id', $client->id)
                 ->assertJsonPath('data.project_id', null);

        $this->assertDatabaseHas('tickets', [
            'client_id' => $client->id,
            'project_id' => null,
            'external_id' => 'exec-client-only',
        ]);
        
        $this->assertNotNull(Ticket::withoutGlobalScopes()->where('external_id', 'exec-client-only')->first()->opened_at);
    }
}
