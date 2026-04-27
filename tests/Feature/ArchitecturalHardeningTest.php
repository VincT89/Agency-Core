<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Project;
use App\Models\Invoice;
use App\Models\Client;
use App\Domain\Finance\Actions\RegisterPaymentAction;

class ArchitecturalHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_fail_closed_project_supremacy_scope()
    {
        $client = Client::create(['name' => 'Acme Srl', 'slug' => 'acme-srl', 'status' => 'active']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project Alpha', 'slug' => 'project-alpha', 'status' => 'active']);

        // 1. Senza auth, la query standard deve tornare 0 risultati
        $this->assertNull(auth()->user());
        
        $countCustom = Project::count();
        $this->assertEquals(0, $countCustom, 'Il ProjectSupremacyScope non è fail-closed!');

        // 2. Con bypass esplicito deve tornare 1
        $countBypassed = Project::withoutGlobalScope(\App\Models\Scopes\ProjectSupremacyScope::class)->count();
        $this->assertEquals(1, $countBypassed, 'Il bypass esplicito dello scope non funziona!');
    }

    public function test_register_payment_action_rolls_back_on_error()
    {
        $client = Client::create(['name' => 'Acme Srl', 'slug' => 'acme-srl', 'status' => 'active']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project Alpha', 'slug' => 'project-alpha', 'status' => 'active']);
        $admin = \App\Models\User::factory()->create();
        $invoice = Invoice::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'created_by' => $admin->id,
            'number' => 'FAT-001',
            'issue_date' => now()->toDateString(),
            'status' => 'issued',
            'currency' => 'EUR',
            'subtotal' => 1000,
            'tax_amount' => 220,
            'total' => 1220,
            'paid_total' => 0,
        ]);

        $action = new RegisterPaymentAction();

        try {
            $action->execute([
                'invoice_id' => $invoice->id,
                'amount' => 200,
                // Questo dovrebbe fallire per assenza di campi obbligatori db level ecc
                // ma per testare il rollback emuliamo un errore forzato db:
                'method' => null, // causa errore SQL NOT NULL
            ]);
        } catch (\Exception $e) {
            // Error atteso
        }

        // Il pagamento non deve esistere
        $this->assertDatabaseMissing('payments', [
            'invoice_id' => $invoice->id,
        ]);

        // Il totale pagato non deve essere alterato
        $invoice->refresh();
        $this->assertEquals(0, $invoice->paid_total);
    }
}
