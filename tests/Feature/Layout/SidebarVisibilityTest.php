<?php

namespace Tests\Feature\Layout;

use Tests\TestCase;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SidebarVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_photographer_sees_correct_sidebar_items()
    {
        $photographer = User::factory()->create(['role' => UserRole::Photographer]);

        $response = $this->actingAs($photographer)->get('/dashboard');

        $response->assertStatus(200);

        // Deve vedere le voci principali
        $response->assertSeeText('Dashboard');
        $response->assertSeeText('Commesse');
        $response->assertSeeText('Shooting');
        $response->assertSeeText('Calendario');
        
        // Deve vedere i Task (nella sezione Operatività)
        $response->assertSeeText('Operatività');
        $response->assertSeeText('Task');

        // NON deve vedere le voci fuori dal suo ambito
        $response->assertDontSeeText('Clienti');
        $response->assertDontSeeText('Team');
        $html = $response->getContent();
        $response->assertDontSee('Ticket');
        $response->assertDontSeeText('Fatture');
        $response->assertDontSeeText('Utenti');
    }

    public function test_admin_sees_correct_sidebar_items()
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertStatus(200);

        // L'admin vede tutto il sistema
        $response->assertSeeText('Dashboard');
        $response->assertSeeText('Clienti');
        $response->assertSeeText('Commesse');
        $response->assertSeeText('Team');
        $response->assertSeeText('Task');
        $response->assertSeeText('Ticket');
        $response->assertSeeText('Fatture');
        $response->assertSeeText('Pagamenti');
        $response->assertSeeText('Utenti');
    }

    public function test_developer_sees_correct_sidebar_items()
    {
        $developer = User::factory()->create(['role' => UserRole::Developer]);

        $response = $this->actingAs($developer)->get('/dashboard');

        $response->assertStatus(200);

        // Developer (come staff operativo) vede progetti, ticket, ecc.
        $response->assertSeeText('Commesse');
        $response->assertSeeText('Ticket');
        $response->assertSeeText('Team');
        $response->assertSeeText('Task');

        // Ma non vede fatture o utenti admin
        $response->assertDontSeeText('Fatture');
        $response->assertDontSeeText('Pagamenti');
        $response->assertDontSeeText('Utenti');
    }
}
