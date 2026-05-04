<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\Project;
use App\Models\MarketingProject;
use App\Models\SocialPost;

class VisibilityScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_visible_to_scope_shows_everything_to_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        
        $client = Client::factory()->create();
        $project = Project::factory()->create(['client_id' => $client->id]);
        
        MarketingProject::factory()->create(['project_id' => $project->id, 'client_id' => $client->id]);
        SocialPost::factory()->create(['project_id' => $project->id, 'client_id' => $client->id]);

        $this->assertEquals(1, Client::visibleTo($admin)->count());
        $this->assertEquals(1, MarketingProject::visibleTo($admin)->count());
        $this->assertEquals(1, SocialPost::visibleTo($admin)->count());
    }

    public function test_visible_to_scope_shows_only_assigned_projects_to_operational_user(): void
    {
        $operationalUser = User::factory()->create(['role' => 'developer']);
        $otherUser = User::factory()->create(['role' => 'developer']);
        
        $this->actingAs($operationalUser);

        // Cliente 1 con Progetto 1 assegnato a operationalUser
        $client1 = Client::factory()->create();
        $project1 = Project::factory()->create(['client_id' => $client1->id]);
        $project1->users()->attach($operationalUser->id, ['role' => 'developer']);
        
        MarketingProject::factory()->create(['project_id' => $project1->id, 'client_id' => $client1->id]);
        SocialPost::factory()->create(['project_id' => $project1->id, 'client_id' => $client1->id]);

        // Cliente 2 con Progetto 2 assegnato a otherUser (NASCOSTO)
        $client2 = Client::factory()->create();
        $project2 = Project::factory()->create(['client_id' => $client2->id]);
        $project2->users()->attach($otherUser->id, ['role' => 'developer']);
        
        MarketingProject::factory()->create(['project_id' => $project2->id, 'client_id' => $client2->id]);
        SocialPost::factory()->create(['project_id' => $project2->id, 'client_id' => $client2->id]);

        // L'utente operativo deve vedere solo 1 di ciascuno
        $this->assertEquals(1, Client::visibleTo($operationalUser)->count());
        $this->assertEquals(1, MarketingProject::visibleTo($operationalUser)->count());
        $this->assertEquals(1, SocialPost::visibleTo($operationalUser)->count());
        
        // Verifica che id corretto venga ritornato
        $this->assertEquals($client1->id, Client::visibleTo($operationalUser)->first()->id);
    }
}
