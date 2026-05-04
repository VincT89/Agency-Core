<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Client;
use App\Models\MarketingProject;

class N8nTicketStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Assicura che ci sia un token configurato di default
        config(['services.n8n.token' => 'valid-test-token']);
    }

    public function test_it_rejects_request_if_token_is_missing(): void
    {
        $response = $this->postJson(route('api.v1.integrations.n8n.tickets.store'), [
            'title' => 'Test Ticket',
        ]);

        $response->assertStatus(401)
                 ->assertJsonPath('message', 'Unauthorized: Token missing');
    }

    public function test_it_rejects_request_if_token_is_invalid(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer wrong-token',
        ])->postJson(route('api.v1.integrations.n8n.tickets.store'), [
            'title' => 'Test Ticket',
        ]);

        $response->assertStatus(403)
                 ->assertJsonPath('message', 'Forbidden: Invalid token');
    }

    public function test_it_fails_closed_if_config_token_is_null_even_with_token_sent(): void
    {
        // Simuliamo una dimenticanza in produzione
        config(['services.n8n.token' => null]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer some-token',
        ])->postJson(route('api.v1.integrations.n8n.tickets.store'), [
            'title' => 'Test Ticket',
        ]);

        $response->assertStatus(403)
                 ->assertJsonPath('message', 'Forbidden: Invalid token');
    }

    public function test_it_creates_a_ticket_with_valid_payload(): void
    {
        // Per CreateTicketFromN8nAction serve un utente admin
        $botUser = User::factory()->create([
            'email' => 'n8n-bot@sodanoconsulting.it',
            'role' => 'admin',
        ]);
        
        // E serve un cliente di fallback o specifico se serve
        $client = Client::factory()->create([
            'company_name' => 'Sodano Consulting Interno',
        ]);

        $marketingProject = MarketingProject::factory()->create();

        $payload = [
            'title' => 'Errore Sincronizzazione N8N',
            'description' => 'Si è verificato un errore durante la sincronizzazione.',
            'priority' => 'high',
            'n8n_execution_id' => '12345',
            'source' => 'n8n_webhook',
            'marketing_project_id' => $marketingProject->id,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer valid-test-token',
        ])->postJson(route('api.v1.integrations.n8n.tickets.store'), $payload);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'data' => [
                         'ticket_id',
                         'code',
                     ]
                 ]);

        $this->assertDatabaseHas('tickets', [
            'title' => 'Errore Sincronizzazione N8N',
            'priority' => 'high',
            'created_by' => $botUser->id,
        ]);
    }
}
