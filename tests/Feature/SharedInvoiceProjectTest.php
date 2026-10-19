<?php

namespace Tests\Feature;

use App\Domain\Finance\Actions\AllocateCreditNote;
use App\Domain\Finance\Actions\ImportIssuedInvoice;
use App\Domain\Finance\Actions\RegisterPaymentAction;
use App\Domain\Finance\Services\InvoiceImportReader;
use App\Enums\UserRole;
use App\Models\BillingProfile;
use App\Models\Client;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\InvoiceImportXml;
use Tests\TestCase;

class SharedInvoiceProjectTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Client $firstClient;
    private Client $secondClient;
    private Project $container;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        Storage::fake('attachments');
        $this->actingAs($this->admin = User::factory()->create(['role' => UserRole::Admin, 'password_changed_at' => now()]));
        $this->container = Project::invoiceContainer();
        $this->firstClient = Client::factory()->create(['name' => 'Primo cliente dimostrativo', 'country_code' => 'IT', 'vat_number' => '01879020517', 'tax_code' => null]);
        $this->secondClient = Client::factory()->create(['name' => 'Secondo cliente dimostrativo', 'country_code' => 'IT', 'vat_number' => '08962440726', 'tax_code' => null]);
        BillingProfile::create(['profile_key' => 'default', 'legal_name' => 'Agenzia dimostrativa', 'vat_country_code' => 'IT',
            'vat_number' => '01234567897', 'fiscal_regime' => 'RF01', 'address' => 'Via di prova 1',
            'postal_code' => '80100', 'city' => 'Napoli', 'province' => 'NA', 'country_code' => 'IT']);
    }

    private function payload(Client $client, string $number, ?int $projectId): array
    {
        return ['client_id' => $client->id, 'project_id' => $projectId, 'number' => $number,
            'issue_date' => '2026-10-19', 'due_date' => '2026-11-19', 'status' => 'draft',
            'currency' => 'EUR', 'subtotal' => '100.00', 'tax_amount' => '22.00'];
    }

    private function import(Client $client, string $number, string $type = 'TD01'): Invoice|CreditNote
    {
        $xml = str_replace('01879020517', $client->vat_number, InvoiceImportXml::make($number, $type));
        $document = app(InvoiceImportReader::class)->read($xml, 'out')[0];

        return app(ImportIssuedInvoice::class)->execute($document,
            ['source' => 'file', 'format' => 'xml', 'filename' => 'prova.xml', 'content' => $xml],
            $client->id, false, $document['due_date']);
    }

    public function test_invoice_projects_include_the_shared_container_and_only_the_selected_clients_projects(): void
    {
        $own = Project::factory()->create(['client_id' => $this->firstClient->id, 'status' => 'active']);
        Project::factory()->create(['client_id' => $this->secondClient->id, 'status' => 'active']);
        Project::factory()->create(['client_id' => null, 'status' => 'active']);
        $response = $this->getJson(route('invoices.client-projects', $this->firstClient))->assertOk()->assertJsonCount(2);
        $this->assertEqualsCanonicalizing([$own->id, $this->container->id], $response->json('*.id'));
        $this->getJson(route('api.client.projects', $this->firstClient))->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $own->id);
        $this->get(route('invoices.create'))->assertOk()->assertSee('data-default-project="'.$this->container->id.'"', false);
        $this->assertNull($this->container->client_id);
    }

    public function test_two_clients_can_use_the_container_without_changing_either_identity(): void
    {
        foreach ([$this->firstClient, $this->secondClient] as $client) {
            $number = 'MANUAL-'.$client->id;
            $this->post(route('invoices.store'), $this->payload($client, $number, $this->container->id))->assertSessionHasNoErrors()->assertRedirect();
            $this->assertDatabaseHas('invoices', ['number' => $number, 'client_id' => $client->id, 'project_id' => $this->container->id]);
        }
        $this->assertSame(1, Project::where('system_key', Project::INVOICE_CONTAINER_KEY)->count());
    }

    public function test_ordinary_foreign_or_unowned_projects_are_still_rejected(): void
    {
        foreach ([$this->secondClient->id, null] as $clientId) {
            $project = Project::factory()->create(['client_id' => $clientId, 'status' => 'active']);
            $this->post(route('invoices.store'), $this->payload($this->firstClient, 'INVALID', $project->id))
                ->assertSessionHasErrors('project_id');
        }
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_editing_an_invoice_can_select_the_container_and_can_still_leave_the_project_empty(): void
    {
        $this->post(route('invoices.store'), $this->payload($this->firstClient, 'EDIT', null))->assertSessionHasNoErrors();
        $invoice = Invoice::sole();
        $this->get(route('invoices.edit', $invoice))->assertOk()->assertSee('/api/invoice-clients/{client}/projects');
        $this->put(route('invoices.update', $invoice), $this->payload($this->firstClient, 'EDIT', $this->container->id))->assertSessionHasNoErrors();
        $this->assertEquals($this->container->id, $invoice->fresh()->project_id);
        $this->put(route('invoices.update', $invoice), $this->payload($this->firstClient, 'EDIT', null))->assertSessionHasNoErrors();
        $this->assertNull($invoice->fresh()->project_id);
    }

    public function test_new_imports_share_the_container_but_reimporting_old_invoices_preserves_their_project(): void
    {
        $old = $this->import($this->firstClient, 'FPR 1/26');
        $own = Project::factory()->create(['client_id' => $this->firstClient->id]);
        foreach ([null, $own->id] as $projectId) {
            $old->update(['project_id' => $projectId]);
            $before = $old->fresh()->getRawOriginal();
            $this->import($this->firstClient, 'FPR 1/26');
            $this->assertSame($before, $old->fresh()->getRawOriginal());
        }
        foreach ([$this->firstClient, $this->secondClient] as $client) {
            $new = $this->import($client, 'FPR NEW-'.$client->id.'/26');
            $this->assertEquals($this->container->id, $new->project_id);
            $this->assertEquals($client->id, $new->client_id);
            $this->assertSame('issued', $new->status);
            $this->assertSame('0.00', $new->paid_total);
        }
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('invoices', 3);
    }

    public function test_payments_and_credit_notes_keep_client_totals_separate_within_the_shared_project(): void
    {
        $first = $this->import($this->firstClient, 'FPR 1/26');
        $second = $this->import($this->secondClient, 'FPR 2/26');
        $note = $this->import($this->firstClient, 'FPR 17/26', 'TD04');
        app(AllocateCreditNote::class)->execute($note, $first->id, '122.00');
        $payment = app(RegisterPaymentAction::class)->execute(['invoice_id' => $second->id,
            'amount' => '20.00', 'payment_date' => '2026-10-19', 'method' => 'bank_transfer']);
        $this->assertEquals($this->secondClient->id, $payment->client_id);
        $this->assertEquals($this->container->id, $payment->project_id);
        $response = $this->get(route('economic-summary.index'))->assertOk();
        $project = $response->viewData('summaryByProject')->sole();
        $this->assertEquals($this->container->id, $project->project_id);
        $this->assertSame('Tutti i clienti', $project->client_name);
        $this->assertEquals(200, $project->total_invoiced);
        $this->assertEquals(20, $project->total_collected);
        $this->assertEquals(102, $project->total_outstanding);
        $clients = $response->viewData('summaryByClient')->keyBy('client_id');
        $this->assertCount(2, $clients);
        $this->assertEquals(100, $clients[$this->firstClient->id]->total_invoiced);
        $this->assertEquals(0, $clients[$this->firstClient->id]->total_outstanding);
        $this->assertEquals(100, $clients[$this->secondClient->id]->total_invoiced);
        $this->assertEquals(20, $clients[$this->secondClient->id]->total_collected);
        $this->assertEquals(102, $clients[$this->secondClient->id]->total_outstanding);
        $this->assertEquals(200, $response->viewData('globalSummary')['total_invoiced']);
    }

    public function test_container_cannot_be_deleted_or_converted_to_a_single_client_project(): void
    {
        $data = ['name' => $this->container->name, 'status' => 'active', 'members' => [$this->admin->id]];
        $this->put(route('projects.update', $this->container), $data + ['client_id' => $this->firstClient->id])->assertSessionHasErrors('client_id');
        $this->put(route('projects.update', $this->container), array_replace($data, ['status' => 'cancelled']))->assertSessionHasErrors('status');
        $this->delete(route('projects.destroy', $this->container))->assertForbidden();
        $this->assertNull($this->container->fresh()->client_id);
        $this->assertSame('active', $this->container->fresh()->status);
        $this->get(route('projects.edit', $this->container))->assertOk()->assertSee('Tutti i clienti.');
    }

    public function test_renaming_the_container_keeps_its_identity_and_does_not_allow_forging_another_container(): void
    {
        $this->put(route('projects.update', $this->container), ['name' => 'Vendite occasionali', 'status' => 'active',
            'members' => [$this->admin->id], 'system_key' => 'tampered'])->assertSessionHasNoErrors();
        $this->assertSame('Vendite occasionali', Project::invoiceContainer()->name);
        $this->post(route('projects.store'), ['name' => 'Altro progetto', 'status' => 'active',
            'members' => [$this->admin->id], 'system_key' => Project::INVOICE_CONTAINER_KEY])->assertSessionHasNoErrors();
        $this->assertSame(1, Project::where('system_key', Project::INVOICE_CONTAINER_KEY)->count());
    }

    public function test_project_selection_remains_finance_only(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Developer, 'password_changed_at' => now()]));
        $this->getJson(route('invoices.client-projects', $this->firstClient))->assertForbidden();
        $this->post(route('invoices.store'), $this->payload($this->firstClient, 'DENIED', $this->container->id))->assertForbidden();
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_migration_preserves_historical_invoices_and_same_named_projects(): void
    {
        $old = $this->import($this->firstClient, 'FPR OLD/26');
        $old->update(['project_id' => null]);
        $before = $old->fresh()->getRawOriginal();
        $migration = require base_path('database/migrations/2026_10_19_120000_add_shared_invoice_project.php');
        $migration->down();
        $previousProjects = DB::table('projects')->get()->toArray();
        $migration->up();
        $this->assertSame($before, $old->fresh()->getRawOriginal());
        foreach ($previousProjects as $project) {
            $this->assertEquals($project, DB::table('projects')->where('id', $project->id)->first(array_keys((array) $project)));
        }
        $this->assertSame(1, Project::where('system_key', Project::INVOICE_CONTAINER_KEY)->count());
        $this->assertNotEquals($this->container->id, Project::invoiceContainer()->id);
    }
}
