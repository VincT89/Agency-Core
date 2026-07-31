<?php

namespace Tests\Feature;

use App\Domain\Finance\Actions\PrepareElectronicInvoiceAction;
use App\Enums\Finance\InvoiceFiscalStatus;
use App\Enums\UserRole;
use App\Models\BillingProfile;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceNumberSequence;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ElectronicInvoicingFoundationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $developer;
    private Client $client;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => UserRole::Admin,
            'password_changed_at' => now(),
        ]);
        $this->developer = User::factory()->create([
            'role' => UserRole::Developer,
            'password_changed_at' => now(),
        ]);
        $this->client = Client::create([
            'name' => 'Cliente Demo',
            'company_name' => 'Cliente Demo Srl',
            'slug' => 'cliente-demo',
            'vat_number' => '12345678903',
            'tax_code' => '12345678903',
            'address' => 'Via Roma 10',
            'postal_code' => '20100',
            'city' => 'Milano',
            'province' => 'MI',
            'country' => 'Italia',
            'country_code' => 'IT',
            'sdi_code' => 'ABC1234',
            'status' => 'active',
        ]);
        $this->project = Project::create([
            'client_id' => $this->client->id,
            'name' => 'Progetto fiscale',
            'slug' => 'progetto-fiscale',
            'status' => 'active',
        ]);
    }

    public function test_only_finance_users_can_manage_the_billing_profile(): void
    {
        $this->actingAs($this->developer)
            ->get(route('billing-profile.edit'))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->put(route('billing-profile.update'), $this->profileData([
                'vat_number' => ' IT 01234567897 ',
                'invoice_series' => ' fe ',
            ]))
            ->assertRedirect(route('billing-profile.edit'));

        $this->assertDatabaseHas('billing_profiles', [
            'profile_key' => 'default',
            'vat_number' => 'IT01234567897',
            'invoice_series' => 'FE',
        ]);
    }

    public function test_incomplete_invoice_does_not_consume_a_fiscal_number(): void
    {
        $this->createBillingProfile();
        $invoice = $this->createInvoice('BOZZA-MANCANTE');

        $invoice->items()->create([
            'description' => 'Servizio senza IVA',
            'quantity' => 1,
            'unit_price' => 100,
            'total' => 100,
        ]);

        $this->actingAs($this->admin)
            ->post(route('invoices.fiscal.prepare', $invoice))
            ->assertSessionHasErrors('fiscal');

        $invoice->refresh();

        $this->assertSame(InvoiceFiscalStatus::NotPrepared, $invoice->fiscal_status);
        $this->assertNull($invoice->fiscal_number);
        $this->assertDatabaseCount('invoice_number_sequences', 0);
    }

    public function test_preparation_assigns_number_locks_snapshot_and_calls_no_provider(): void
    {
        Http::fake();
        $this->createBillingProfile();
        $invoice = $this->createReadyInvoice('BOZZA-PRONTA');

        $this->actingAs($this->admin)
            ->post(route('invoices.fiscal.prepare', $invoice))
            ->assertRedirect(route('invoices.show', $invoice))
            ->assertSessionHas(
                'success',
                'Fattura fiscale preparata e bloccata. Non è stata inviata ad Aruba né allo SdI.'
            );

        $invoice->refresh();

        $this->assertSame(InvoiceFiscalStatus::Ready, $invoice->fiscal_status);
        $this->assertSame('FE-2026-0001', $invoice->fiscal_number);
        $this->assertNotNull($invoice->fiscal_locked_at);
        $this->assertSame('Cliente Demo Srl', $invoice->fiscal_snapshot['customer']['legal_name']);
        $this->assertSame('122.00', $invoice->fiscal_snapshot['document']['total']);
        $this->assertSame('22.00', $invoice->fiscal_snapshot['lines'][0]['vat_rate']);
        $this->assertDatabaseHas('invoice_number_sequences', [
            'year' => 2026,
            'series' => 'FE',
            'next_number' => 2,
        ]);
        Http::assertNothingSent();
    }

    public function test_prepared_invoice_cannot_be_edited_or_deleted(): void
    {
        $this->createBillingProfile();
        $invoice = $this->createReadyInvoice('BOZZA-BLOCCO');

        $this->actingAs($this->admin);
        app(PrepareElectronicInvoiceAction::class)->execute($invoice);

        $this->get(route('invoices.edit', $invoice))->assertForbidden();
        $this->delete(route('invoices.destroy', $invoice))->assertForbidden();
        $this->post(route('invoices.items.store', $invoice), [
            'description' => 'Tentativo',
            'quantity' => 1,
            'unit_price' => 1,
            'vat_rate' => 22,
        ])->assertForbidden();
    }

    public function test_reopening_keeps_reserved_number_and_allows_corrections(): void
    {
        $this->createBillingProfile();
        $invoice = $this->createReadyInvoice('BOZZA-RIAPERTURA');

        $this->actingAs($this->admin);
        app(PrepareElectronicInvoiceAction::class)->execute($invoice);
        $reservedNumber = $invoice->fresh()->fiscal_number;

        $this->post(route('invoices.fiscal.reopen', $invoice))
            ->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();

        $this->assertSame(InvoiceFiscalStatus::NotPrepared, $invoice->fiscal_status);
        $this->assertSame($reservedNumber, $invoice->fiscal_number);
        $this->assertNull($invoice->fiscal_snapshot);
        $this->assertNull($invoice->fiscal_locked_at);
        $this->get(route('invoices.edit', $invoice))->assertOk();

        app(PrepareElectronicInvoiceAction::class)->execute($invoice);

        $this->assertSame($reservedNumber, $invoice->fresh()->fiscal_number);
        $this->assertSame(2, InvoiceNumberSequence::query()->value('next_number'));
    }

    public function test_repeated_preparation_is_idempotent(): void
    {
        $this->createBillingProfile();
        $invoice = $this->createReadyInvoice('BOZZA-DOPPIO-CLICK');

        $this->actingAs($this->admin);
        $action = app(PrepareElectronicInvoiceAction::class);
        $action->execute($invoice);
        $firstSnapshot = $invoice->fresh()->fiscal_snapshot;
        $action->execute($invoice);

        $this->assertSame(2, InvoiceNumberSequence::query()->value('next_number'));
        $this->assertSame($firstSnapshot, $invoice->fresh()->fiscal_snapshot);
    }

    public function test_numbering_is_progressive_and_separate_for_each_year(): void
    {
        $this->createBillingProfile(['initial_sequence' => 10]);
        $first = $this->createReadyInvoice('BOZZA-2026-A', '2026-07-31');
        $second = $this->createReadyInvoice('BOZZA-2026-B', '2026-08-01');
        $nextYear = $this->createReadyInvoice('BOZZA-2027-A', '2027-01-10');

        $this->actingAs($this->admin);
        $action = app(PrepareElectronicInvoiceAction::class);
        $action->execute($first);
        $action->execute($second);
        $action->execute($nextYear);

        $this->assertSame('FE-2026-0010', $first->fresh()->fiscal_number);
        $this->assertSame('FE-2026-0011', $second->fresh()->fiscal_number);
        $this->assertSame('FE-2027-0010', $nextYear->fresh()->fiscal_number);
    }

    public function test_invoice_creation_calculates_tax_from_lines_instead_of_trusting_totals(): void
    {
        $this->actingAs($this->admin)
            ->post(route('invoices.store'), [
                'client_id' => $this->client->id,
                'project_id' => $this->project->id,
                'number' => 'BOZZA-CALCOLO',
                'issue_date' => '2026-07-31',
                'due_date' => '2026-08-30',
                'status' => 'draft',
                'currency' => 'EUR',
                'fiscal_document_type' => 'TD01',
                'subtotal' => 999,
                'tax_amount' => 999,
                'paid_total' => 0,
                'items' => [[
                    'description' => 'Servizio',
                    'quantity' => 2,
                    'unit_price' => 50,
                    'unit_of_measure' => 'NR',
                    'vat_rate' => 22,
                ]],
            ])
            ->assertRedirect();

        $invoice = Invoice::query()->where('number', 'BOZZA-CALCOLO')->firstOrFail();

        $this->assertSame('100.00', $invoice->subtotal);
        $this->assertSame('22.00', $invoice->tax_amount);
        $this->assertSame('122.00', $invoice->total);
        $this->assertSame('122.00', $invoice->items()->firstOrFail()->total_with_tax);
    }

    private function createBillingProfile(array $overrides = []): BillingProfile
    {
        return BillingProfile::create($this->profileData($overrides));
    }

    private function profileData(array $overrides = []): array
    {
        return array_merge([
            'profile_key' => 'default',
            'legal_name' => 'Agenzia Demo Srl',
            'vat_country_code' => 'IT',
            'vat_number' => '01234567897',
            'tax_code' => '01234567897',
            'fiscal_regime' => 'RF01',
            'address' => 'Via Verdi 1',
            'postal_code' => '80100',
            'city' => 'Napoli',
            'province' => 'NA',
            'country_code' => 'IT',
            'email' => 'amministrazione@example.test',
            'pec' => 'agenzia@pec.example.test',
            'recipient_code' => 'ABC1234',
            'iban' => 'IT60X0542811101000000123456',
            'default_payment_method' => 'MP05',
            'invoice_series' => 'FE',
            'initial_sequence' => 1,
        ], $overrides);
    }

    private function createInvoice(
        string $number,
        string $issueDate = '2026-07-31',
    ): Invoice {
        return Invoice::create([
            'client_id' => $this->client->id,
            'project_id' => $this->project->id,
            'created_by' => $this->admin->id,
            'number' => $number,
            'issue_date' => $issueDate,
            'due_date' => '2026-08-30',
            'status' => 'draft',
            'currency' => 'EUR',
            'subtotal' => 100,
            'tax_amount' => 22,
            'total' => 122,
            'paid_total' => 0,
        ]);
    }

    private function createReadyInvoice(
        string $number,
        string $issueDate = '2026-07-31',
    ): Invoice {
        $invoice = $this->createInvoice($number, $issueDate);

        $invoice->items()->create([
            'description' => 'Servizio professionale',
            'quantity' => 1,
            'unit_price' => 100,
            'unit_of_measure' => 'NR',
            'vat_rate' => 22,
            'total' => 100,
            'tax_amount' => 22,
            'total_with_tax' => 122,
        ]);

        return $invoice;
    }
}
