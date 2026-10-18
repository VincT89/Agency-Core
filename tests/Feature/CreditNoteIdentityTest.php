<?php

namespace Tests\Feature;

use App\Domain\Finance\Actions\ImportIssuedInvoice;
use App\Domain\Finance\Services\InvoiceImportReader;
use App\Enums\UserRole;
use App\Models\BillingProfile;
use App\Models\Client;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\InvoiceImportXml;
use Tests\TestCase;

class CreditNoteIdentityTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Bus::fake();
        Storage::fake('attachments');
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin, 'password_changed_at' => now()]));
        $this->client = Client::factory()->create(['name' => 'Cliente dimostrativo', 'country_code' => 'IT', 'vat_number' => '01879020517', 'tax_code' => null]);
        BillingProfile::create(['profile_key' => 'default', 'legal_name' => 'Agenzia dimostrativa', 'vat_country_code' => 'IT',
            'vat_number' => '01234567897', 'fiscal_regime' => 'RF01', 'address' => 'Via di prova 1',
            'postal_code' => '80100', 'city' => 'Napoli', 'province' => 'NA', 'country_code' => 'IT']);
    }

    private function import(string $xml): Invoice|CreditNote
    {
        $data = app(InvoiceImportReader::class)->read($xml, 'out')[0];

        return app(ImportIssuedInvoice::class)->execute($data, ['filename' => 'documento.xml', 'content' => $xml, 'format' => 'xml', 'source' => 'file'],
            $this->client->id, false, $data['due_date']);
    }

    private function snapshot(): array
    {
        $rows = [];
        foreach (['invoices', 'credit_notes', 'credit_note_allocations', 'clients', 'payments', 'attachments', 'audit_logs'] as $table) {
            $rows[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $rows;
    }

    public function test_five_line_credit_suggests_each_xml_amount_and_only_the_present_matching_invoice_without_writes(): void
    {
        $invoiceXml = strtr(InvoiceImportXml::make('FPR 6/26'), ['100.00' => '900.00', '122.00' => '1098.00', '<Imposta>22.00</Imposta>' => '<Imposta>198.00</Imposta>']);
        $invoice = $this->import($invoiceXml);
        $xml = InvoiceImportXml::make('FPR 17/26', 'TD04');
        $lines = '';
        foreach (['41/25' => '1200.00', '49/25' => '900.00', '55/25' => '900.00', '63/25' => '900.00', '6/26' => '900.00'] as $number => $amount) {
            $lines .= '<DettaglioLinee><Descrizione>Storno fattura '.$number.'</Descrizione><Quantita>1</Quantita><PrezzoUnitario>'.$amount.'</PrezzoUnitario><PrezzoTotale>'.$amount.'</PrezzoTotale><AliquotaIVA>22.00</AliquotaIVA></DettaglioLinee>';
        }
        $xml = preg_replace('/<DettaglioLinee>.*?<\/DettaglioLinee>/s', $lines, $xml);
        $xml = strtr($xml, ['122.00' => '5856.00', '<ImponibileImporto>100.00</ImponibileImporto>' => '<ImponibileImporto>4800.00</ImponibileImporto>',
            '<Imposta>22.00</Imposta>' => '<Imposta>1056.00</Imposta>']);
        $note = $this->import($xml);
        $before = $this->snapshot();
        $response = $this->get(route('credit-notes.show', $note))->assertOk()->assertSee('Partita IVA cliente nel documento')
            ->assertSee('IT 01879020517')->assertSee('1.464,00')->assertSee('1.098,00')->assertSee('Usa suggerimento FPR 6/26');
        $references = $response->viewData('references');
        $this->assertSame(['1464.00', '1098.00', '1098.00', '1098.00', '1098.00'], $references->pluck('suggested_amount')->all());
        $this->assertSame([false, false, false, false, true], $references->pluck('can_prefill')->all());
        $this->assertTrue($invoice->is($references->last()['candidates']->sole()));
        $this->assertSame($before, $this->snapshot());
        $this->assertTrue($note->is($this->import($xml)));
        $this->assertSame($before, $this->snapshot());
    }

    public function test_country_vat_and_issuer_conflicts_are_excluded_and_rejected_even_with_the_same_client_id(): void
    {
        $invoice = $this->import(InvoiceImportXml::make());
        $note = $this->import(InvoiceImportXml::make('FPR 17/26', 'TD04', description: 'Storno fattura 1/26'));
        $original = $invoice->import_metadata;
        foreach ([['counterparty', 'vat_number', '12345678903'], ['counterparty', 'country_code', 'DE'],
            ['agency', 'vat_number', '12345678903'], ['agency', 'country_code', 'DE'], ['counterparty', 'vat_number', '']] as [$party, $field, $value]) {
            $metadata = $original;
            $metadata[$party][$field] = $value;
            $invoice->update(['import_metadata' => $metadata]);
            $before = $this->snapshot();
            $response = $this->get(route('credit-notes.show', $note))->assertOk()->assertDontSee('Usa suggerimento');
            $this->assertCount(0, $response->viewData('invoices'));
            $this->post(route('credit-notes.allocate', $note), ['invoice_id' => $invoice->id, 'amount' => '122.00'])->assertSessionHasErrors('invoice_id');
            $this->assertSame($before, $this->snapshot());
        }
    }

    public function test_edited_client_details_do_not_replace_original_xml_identities(): void
    {
        $invoice = $this->import(InvoiceImportXml::make());
        $note = $this->import(InvoiceImportXml::make('FPR 17/26', 'TD04', description: 'Storno fattura 1/26'));
        $this->client->update(['country_code' => 'DE', 'vat_number' => '12345678903', 'name' => 'Anagrafica modificata']);
        $this->get(route('credit-notes.show', $note))->assertOk()->assertSee('IT 01879020517')->assertSee('Usa suggerimento FPR 1/26');
        $this->post(route('credit-notes.allocate', $note), ['invoice_id' => $invoice->id, 'amount' => '122.00'])->assertSessionHasNoErrors();
        $this->assertSame('credited', $invoice->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
        $this->get(route('credit-notes.show', $note))->assertOk()->assertDontSee('Usa suggerimento')->assertSee('La fattura è già collegata');
    }

    public function test_country_and_tax_code_are_required_when_both_documents_have_no_vat_number(): void
    {
        $this->client->update(['vat_number' => null, 'tax_code' => 'RSSMRA80A01H501U']);
        $withoutVat = fn ($xml) => str_replace('<IdFiscaleIVA><IdPaese>IT</IdPaese><IdCodice>01879020517</IdCodice></IdFiscaleIVA>', '<CodiceFiscale>RSSMRA80A01H501U</CodiceFiscale>', $xml);
        $invoice = $this->import($withoutVat(InvoiceImportXml::make()));
        $note = $this->import($withoutVat(InvoiceImportXml::make('FPR 17/26', 'TD04', description: 'Storno fattura 1/26')));
        $this->get(route('credit-notes.show', $note))->assertOk()->assertSee('Codice fiscale cliente nel documento')->assertSee('Usa suggerimento FPR 1/26');
        $metadata = $invoice->import_metadata;
        $metadata['counterparty']['tax_code'] = 'BNCLGU80A01H501W';
        $invoice->update(['import_metadata' => $metadata]);
        $this->post(route('credit-notes.allocate', $note), ['invoice_id' => $invoice->id, 'amount' => '122.00'])->assertSessionHasErrors('invoice_id');
        $this->assertDatabaseCount('credit_note_allocations', 0);
    }

    public function test_same_vat_with_conflicting_tax_codes_does_not_identify_the_same_client(): void
    {
        $invoice = $this->import(InvoiceImportXml::make());
        $note = $this->import(InvoiceImportXml::make('FPR 17/26', 'TD04', description: 'Storno fattura 1/26'));
        $invoiceData = $invoice->import_metadata;
        $noteData = $note->import_metadata;
        $invoiceData['counterparty']['tax_code'] = '01879020517';
        $noteData['counterparty']['tax_code'] = '12345678903';
        $invoice->update(['import_metadata' => $invoiceData]);
        $note->update(['import_metadata' => $noteData]);
        $this->get(route('credit-notes.show', $note))->assertOk()->assertDontSee('Usa suggerimento');
        $this->post(route('credit-notes.allocate', $note), ['invoice_id' => $invoice->id, 'amount' => '122.00'])->assertSessionHasErrors('invoice_id');
        $this->assertDatabaseCount('credit_note_allocations', 0);
    }

    public function test_prefix_ambiguity_and_mismatching_fiscal_year_never_prefill_a_link(): void
    {
        $this->import(InvoiceImportXml::make('FPR 6/26'));
        $this->import(InvoiceImportXml::make('FPA 6/26'));
        $note = $this->import(InvoiceImportXml::make('FPR 17/26', 'TD04', description: 'Storno fattura 6/26'));
        $response = $this->get(route('credit-notes.show', $note))->assertOk()->assertDontSee('Usa suggerimento');
        $this->assertCount(2, $response->viewData('references')->sole()['candidates']);
        $explicit = $this->import(InvoiceImportXml::make('FPR 18/26', 'TD04', description: 'Storno fattura FPR 6/26'));
        $this->get(route('credit-notes.show', $explicit))->assertOk()->assertSee('Usa suggerimento FPR 6/26');
        $this->import(str_replace('2026-', '2025-', InvoiceImportXml::make('FPR 8/26')));
        $wrongYear = $this->import(InvoiceImportXml::make('FPR 19/26', 'TD04', description: 'Storno fattura 8/26'));
        $this->get(route('credit-notes.show', $wrongYear))->assertOk()->assertDontSee('Usa suggerimento');
    }

    public function test_ambiguous_lines_and_non_reconciling_totals_require_manual_amounts(): void
    {
        $this->import(InvoiceImportXml::make());
        $note = $this->import(InvoiceImportXml::make('FPR 17/26', 'TD04', description: 'Storno fattura 1/26 e fattura 2/26'));
        $response = $this->get(route('credit-notes.show', $note))->assertOk()->assertDontSee('Usa suggerimento');
        $this->assertNull($response->viewData('references')->sole()['suggested_amount']);
        $rounded = $this->import(str_replace('<ImportoTotaleDocumento>122.00', '<ImportoTotaleDocumento>122.01', InvoiceImportXml::make('FPR 18/26', 'TD04', description: 'Storno fattura 1/26')));
        $this->assertNull($this->get(route('credit-notes.show', $rounded))->assertOk()->viewData('references')->sole()['suggested_amount']);
    }

    public function test_split_credit_suggests_the_net_amount_and_duplicate_clients_are_not_merged(): void
    {
        $invoice = $this->import(InvoiceImportXml::make('FPA 1/26', split: true));
        $note = $this->import(InvoiceImportXml::make('FPA 2/26', 'TD04', true, 'Storno fattura FPA 1/26'));
        $response = $this->get(route('credit-notes.show', $note))->assertOk();
        $this->assertSame('100.00', $response->viewData('references')->sole()['suggested_amount']);
        $this->assertTrue($response->viewData('references')->sole()['can_prefill']);
        $duplicate = Client::factory()->create(['country_code' => 'IT', 'vat_number' => '01879020517']);
        $invoice->update(['client_id' => $duplicate->id]);
        $this->get(route('credit-notes.show', $note))->assertOk()->assertDontSee('Usa suggerimento');
        $this->post(route('credit-notes.allocate', $note), ['invoice_id' => $invoice->id, 'amount' => '100.00'])->assertSessionHasErrors('invoice_id');
        $this->assertDatabaseCount('credit_note_allocations', 0);
    }
}
