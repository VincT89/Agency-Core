<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarEventAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_cannot_see_unrelated_project_event()
    {
        $manager = User::factory()->create(['role' => UserRole::Administration, 'password_changed_at' => now()]);
        
        $client = Client::create(['name' => 'Test Client', 'slug' => 'test-client', 'status' => 'active']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project A', 'slug' => 'project-a', 'status' => 'active']);
        
        // Evento di un progetto non assegnato al manager
        $event = CalendarEvent::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'created_by' => User::factory()->create(['role' => UserRole::Admin])->id,
            'title' => 'Riunione Segreta',
            'type' => 'internal_meeting',
            'status' => 'scheduled',
            'start_at' => now()->addDays(2),
            'end_at' => now()->addDays(2)->addHour(),
        ]);

        $response = $this->actingAs($manager)->get(route('calendar-events.show', $event));
        $response->assertForbidden();
    }

    public function test_manager_can_see_project_event_in_their_perimeter()
    {
        $manager = User::factory()->create(['role' => UserRole::Administration, 'password_changed_at' => now()]);
        $client = Client::create(['name' => 'Test Client', 'slug' => 'test-client-2', 'status' => 'active']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project B', 'slug' => 'project-b', 'status' => 'active']);
        
        $manager->projects()->attach($project->id, ['role' => 'manager']); // Assegna il perimetro

        $event = CalendarEvent::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'created_by' => User::factory()->create(['role' => UserRole::Admin])->id,
            'title' => 'Riunione Progetto',
            'type' => 'client_meeting',
            'status' => 'scheduled',
            'start_at' => now()->addDays(2),
            'end_at' => now()->addDays(2)->addHour(),
        ]);

        $response = $this->actingAs($manager)->get(route('calendar-events.show', $event));
        $response->assertOk();
    }

    public function test_manager_cannot_see_personal_event_of_someone_else()
    {
        $manager = User::factory()->create(['role' => UserRole::Administration, 'password_changed_at' => now()]);
        
        // Evento Personale (senza progetto e cliente) di un altro utente
        $event = CalendarEvent::create([
            'created_by' => User::factory()->create(['role' => UserRole::Admin])->id,
            'title' => 'Pausa Pranzo',
            'type' => 'other',
            'status' => 'scheduled',
            'start_at' => now()->addDays(2),
            'end_at' => now()->addDays(2)->addHour(),
        ]);

        $response = $this->actingAs($manager)->get(route('calendar-events.show', $event));
        $response->assertForbidden();
    }

    public function test_event_without_end_at_gets_normalized_correctly()
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'password_changed_at' => now()]);

        $startAt = now()->addDays(5)->format('Y-m-d\TH:i');

        $response = $this->actingAs($admin)->post(route('calendar-events.store'), [
            'title' => 'Evento Istantaneo',
            'type' => 'internal_meeting',
            'status' => 'scheduled',
            'start_at' => $startAt,
            // 'end_at' => volutamente ometto
        ]);

        $response->assertRedirect();
        
        $event = CalendarEvent::where('title', 'Evento Istantaneo')->first();
        $this->assertNotNull($event);
        
        // Verifica che end_at sia stato valorizzato paritetico a start_at (o comunque valido format-wise DB)
        $this->assertEquals($event->start_at, $event->end_at);
    }
}
