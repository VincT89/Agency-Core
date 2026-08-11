<?php

namespace Tests\Feature\Livewire\Admin\Social;

use App\Enums\Social\AgencyConnectionStatus;
use App\Enums\UserRole;
use App\Livewire\Admin\Social\AgencySocialConnections;
use App\Models\AgencySocialConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AgencySocialConnectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_explains_the_difference_between_meta_and_client_tiktok_connections(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(AgencySocialConnections::class)
            ->assertSee("Connessioni Meta dell'agenzia")
            ->assertSee('Qui si gestiscono soltanto le connessioni Meta condivise')
            ->assertSee('TikTok viene invece collegato dalla scheda del singolo cliente')
            ->assertSee('Collega account Meta')
            ->assertDontSee('Connessioni Social Agenzia');
    }

    public function test_existing_connection_uses_clear_sync_and_revoke_actions(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        AgencySocialConnection::forceCreate([
            'provider' => 'facebook',
            'provider_user_name' => 'Account Agenzia',
            'access_token' => 'agency-token',
            'status' => AgencyConnectionStatus::Connected,
            'requires_reauth' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(AgencySocialConnections::class)
            ->assertSee('Aggiungi account Meta')
            ->assertSee('Sincronizza profili')
            ->assertSee('Revoca connessione');
    }
}
