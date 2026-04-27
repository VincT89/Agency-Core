<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class TicketCodeGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_creation_generates_correct_code()
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'password_changed_at' => now()]);
        $client = new Client();
        $client->name = 'Test Client';
        $client->slug = 'test-client';
        $client->status = 'active';
        $client->save();

        $response = $this->actingAs($admin)->post('/tickets', [
            'client_id' => $client->id,
            'title' => 'Nuovo Problema',
            'type' => 'support',
            'status' => 'open',
            'priority' => 'high',
        ]);

        $response->assertRedirect();
        
        $ticket = Ticket::first();

        // Verifica che il codice esista e abbia il formato TCK-YYYY-NNNNNN
        $this->assertNotNull($ticket->code);
        $expectedCode = sprintf('TCK-%s-%06d', now()->format('Y'), $ticket->id);
        $this->assertEquals($expectedCode, $ticket->code);
    }

    public function test_backfill_command_generates_codes_for_old_tickets()
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $client = new Client();
        $client->name = 'Test Client';
        $client->slug = 'test-client-2';
        $client->status = 'active';
        $client->save();

        // Creiamo un ticket aggirando l'observer per forzare il codice nullo
        $ticket = Ticket::withoutEvents(function () use ($admin, $client) {
            return Ticket::create([
                'client_id' => $client->id,
                'created_by' => $admin->id,
                'title' => 'Vecchio Problema',
                'type' => 'bug',
                'status' => 'open',
                'priority' => 'low',
                'code' => null, // Fortemente nullo
            ]);
        });

        $this->assertNull($ticket->fresh()->code);

        // Eseguiamo il command
        Artisan::call('app:backfill-ticket-codes');

        // Verifichiamo che il command lo abbia backfillato
        $ticket->refresh();
        $this->assertNotNull($ticket->code);
        $expectedCode = sprintf('TCK-%s-%06d', $ticket->created_at->format('Y'), $ticket->id);
        $this->assertEquals($expectedCode, $ticket->code);
    }
}
