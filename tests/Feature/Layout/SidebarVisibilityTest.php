<?php

namespace Tests\Feature\Layout;

use App\Enums\UserRole;
use App\Models\MarketingCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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
        $response->assertSeeText('Progetti');
        $response->assertSeeText('Shooting');
        $response->assertSeeText('Calendario');
        $response->assertSeeText('Le mie disponibilità');

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
        $response->assertDontSeeText('Disponibilità team');
    }

    public function test_admin_sees_correct_sidebar_items()
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertStatus(200);

        // L'admin vede tutto il sistema
        $response->assertSeeText('Dashboard');
        $response->assertSeeText('Clienti');
        $response->assertSeeText('Progetti');
        $response->assertSeeText('Team');
        $response->assertSeeText('Task');
        $response->assertSeeText('Ticket');
        $response->assertSeeText('Fatture');
        $response->assertSeeText('Pagamenti');
        $response->assertSeeText('Utenti');
        $response->assertSeeText('Le mie disponibilità');
        $response->assertSeeText('Disponibilità team');
    }

    public function test_dummy()
    {
        $this->withoutExceptionHandling();
        $user = User::factory()->create(['role' => UserRole::Developer]);
        $this->actingAs($user); // IMPORTANT!

        $counts = [
            'marketingProjectsCount' => MarketingCampaign::visibleTo($user)->whereIn('status', ['draft', 'active'])->count(),
        ];
        $this->assertTrue(true);
    }

    public function test_developer_sees_correct_sidebar_items()
    {
        $developer = User::factory()->create(['role' => UserRole::Developer]);

        $response = $this->actingAs($developer)->get('/dashboard');

        $response->assertStatus(200);

        // Developer (come staff operativo) vede progetti, ticket, ecc.
        $response->assertSeeText('Progetti');
        $response->assertSeeText('Ticket');
        $response->assertSeeText('Task');
        $response->assertSeeText('Le mie disponibilità');

        // Ma non vede fatture o utenti admin
        $response->assertDontSeeText('Fatture');
        $response->assertDontSeeText('Pagamenti');
        $response->assertDontSeeText('Utenti');
        $response->assertDontSeeText('Team');
        $response->assertDontSeeText('Disponibilità team');
    }
}
