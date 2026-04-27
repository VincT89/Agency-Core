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
        
        $this->actingAs($admin)->post(route('clients.store'), [
            'name' => 'Acme Test Srl',
            'status' => 'active',
            'email' => 'info@acmetest.it',
        ])->assertRedirect();

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
        $this->assertStringContainsString('ha aggiornato lo stato de il cliente Acme Test Srl a \'Inactive\'', $updateLog->description);
        
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
        
        $this->actingAs($manager)
             ->get(route('audit-logs.index'))
             ->assertForbidden();

        $this->actingAs($admin)
             ->get(route('audit-logs.index'))
             ->assertOk();
    }
}
