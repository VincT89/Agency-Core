<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\Invoice;
use App\Models\AuditLog;
use App\Models\Attachment;
use App\Enums\UserRole;
use Carbon\Carbon;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_and_update_client_generates_readable_logs(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'password_changed_at' => now()]);
        
        $this->mock(\App\Services\Integrations\Nextcloud\NextcloudService::class, function ($mock) {
            $mock->shouldReceive('ensureClientMediaDirectories')->andReturn([
                'photo' => '/mock_root/acme_test',
                'video' => '/mock_video_root/acme_test',
            ]);
        });

        $this->actingAs($admin)->post(route('clients.store'), [
            'name' => 'Acme Test Srl',
            'status' => 'active',
            'email' => 'info@acmetest.it',
            'nextcloud_folder_name' => 'acme_test',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $client = Client::where('name', 'Acme Test Srl')->first();
        
        $createLog = AuditLog::where('auditable_type', $client->getMorphClass())
            ->where('auditable_id', $client->id)
            ->where('action', 'created')
            ->first();

        $this->assertNotNull($createLog);
        $this->assertStringContainsString('ha creato il cliente Acme Test Srl', $createLog->description);
        $this->assertNull($createLog->new_values); // Payload pulito su create

        // UPDATE
        $this->actingAs($admin)->patch(route('clients.update', $client), [
            'name' => 'Acme Test Srl', // invariato
            'status' => 'inactive',    // variato (tracked)
            'email' => 'new@acmetest.it', // variato (untracked)
        ])->assertRedirect();

        $updateLog = AuditLog::where('auditable_type', $client->getMorphClass())
            ->where('auditable_id', $client->id)
            ->where('action', 'status_changed')
            ->first();

        $this->assertNotNull($updateLog);
        $this->assertStringContainsString("ha impostato il cliente Acme Test Srl come 'Inattivo'", $updateLog->description);
        $this->assertSame(
            'ha impostato il cliente Acme Test Srl come Inattivo.',
            $updateLog->display_action_text
        );
        
        // Verifica del filtro JSON
        $this->assertArrayHasKey('status', $updateLog->new_values);
        $this->assertArrayNotHasKey('email', $updateLog->new_values);
    }

    public function test_update_on_untracked_field_does_not_log(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'password_changed_at' => now()]);
        
        $client = Client::create([
            'name' => 'Acme',
            'slug' => 'acme',
            'status' => 'active',
            'email' => 'old@email.it',
        ]);

        AuditLog::query()->delete(); // clear logs of creation

        // Modifica solo campo untracked (email)
        $this->actingAs($admin)->patch(route('clients.update', $client), [
            'name' => 'Acme',
            'status' => 'active',
            'email' => 'new@email.it',
        ]);

        $logCount = AuditLog::where('auditable_id', $client->id)->count();
        $this->assertEquals(0, $logCount, 'Modifiche a campi non tracciati non dovrebbero generare audit logs');
    }

    public function test_global_audit_log_access(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'password_changed_at' => now()]);
        $manager = User::factory()->create(['role' => UserRole::Administration, 'password_changed_at' => now()]);

        $this->assertTrue($admin->canViewAuditLogs());
        $this->assertFalse($manager->canViewAuditLogs());
        
        $this->actingAs($manager)
             ->get(route('audit-logs.index'))
             ->assertForbidden();

        $this->actingAs($admin)
             ->get(route('audit-logs.index'))
             ->assertOk();
    }

    public function test_audit_log_page_uses_clear_language_and_hides_raw_payloads(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'password_changed_at' => now()]);
        $actor = User::factory()->create(['role' => UserRole::Marketing, 'name' => 'Mario Marketing']);
        $client = Client::factory()->createQuietly(['name' => 'Cliente Registro']);

        AuditLog::query()->delete();
        AuditLog::create([
            'user_id' => $actor->id,
            'action' => 'status_changed',
            'auditable_type' => $client->getMorphClass(),
            'auditable_id' => $client->id,
            'old_values' => ['status' => 'active'],
            'new_values' => ['status' => 'inactive'],
            'description' => 'PAYLOAD_TECNICO_DA_NON_MOSTRARE',
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('audit-logs.index'))
            ->assertOk()
            ->assertSeeText('Registro attività')
            ->assertSeeText('Mario Marketing')
            ->assertSeeText('ha impostato il cliente Cliente Registro come Inattivo.')
            ->assertDontSeeText('Mostra dettagli tecnici')
            ->assertDontSeeText('PAYLOAD_TECNICO_DA_NON_MOSTRARE');
    }

    public function test_audit_log_filters_support_shootings_actions_users_and_dates(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'password_changed_at' => now()]);
        $shootingActor = User::factory()->create(['role' => UserRole::Marketing]);
        $loginActor = User::factory()->create(['role' => UserRole::Developer]);

        AuditLog::query()->delete();
        AuditLog::create([
            'user_id' => $shootingActor->id,
            'action' => 'status_changed',
            'auditable_type' => \App\Models\Shooting\Shoot::class,
            'auditable_id' => 999,
            'old_values' => ['status' => 'waiting_client'],
            'new_values' => ['status' => 'scheduled'],
            'created_at' => now(),
        ]);
        AuditLog::create([
            'user_id' => $loginActor->id,
            'action' => 'login',
            'auditable_type' => User::class,
            'auditable_id' => $loginActor->id,
            'created_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)
            ->get(route('audit-logs.index', [
                'auditable_type' => 'shootings',
                'action' => 'status_changed',
                'user_id' => $shootingActor->id,
                'date_from' => today()->toDateString(),
                'date_to' => today()->toDateString(),
            ]))
            ->assertOk()
            ->assertSeeText('ha impostato lo shooting selezionato come Pianificato.')
            ->assertDontSeeText("ha effettuato l'accesso.");
    }

    public function test_audit_log_navigation_is_visible_only_to_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'password_changed_at' => now()]);
        $administration = User::factory()->create([
            'role' => UserRole::Administration,
            'password_changed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Registro attività');

        $this->actingAs($administration)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSeeText('Registro attività');
    }
}
