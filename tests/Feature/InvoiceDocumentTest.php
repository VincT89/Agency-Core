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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\InvoiceImportXml;
use Tests\TestCase;

class InvoiceDocumentTest extends TestCase
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
        $this->client = Client::factory()->create(['name' => 'Anagrafica attuale', 'vat_number' => '01879020517', 'tax_code' => null, 'country_code' => 'IT']);
        BillingProfile::create(['profile_key' => 'default', 'legal_name' => 'Agenzia dimostrativa', 'vat_country_code' => 'IT',
            'vat_number' => '01234567897', 'fiscal_regime' => 'RF01', 'address' => 'Via di prova 1',
            'postal_code' => '80100', 'city' => 'Napoli', 'province' => 'NA', 'country_code' => 'IT']);
    }

    private function import(?string $xml = null, int $index = 0): Invoice|CreditNote
    {
        $xml ??= InvoiceImportXml::make();
        $document = app(InvoiceImportReader::class)->read($xml, 'out')[$index];

        return app(ImportIssuedInvoice::class)->execute($document,
            ['format' => 'xml', 'source' => 'file', 'filename' => 'documento.xml', 'content' => $xml],
            $this->client->id, false, $document['due_date']);
    }

    public function test_preview_reads_the_original_parties_and_payment_details_without_writing_or_requiring_current_billing_settings(): void
    {
        $xml = str_replace('<DettaglioPagamento>', '<DettaglioPagamento><ModalitaPagamento>MP05</ModalitaPagamento><IBAN>IBAN-DIMOSTRATIVO</IBAN>', InvoiceImportXml::make());
        $invoice = $this->import($xml);
        $this->client->update(['name' => 'Nome modificato dopo importazione', 'address' => 'Indirizzo modificato']);
        BillingProfile::query()->update(['legal_name' => 'Profilo modificato', 'vat_number' => '']);
        $tables = ['invoices', 'credit_notes', 'clients', 'payments', 'attachments', 'audit_logs'];
        $snapshot = fn () => array_map(fn ($table) => DB::table($table)->orderBy('id')->get()->toJson(), $tables);
        $before = $snapshot();

        $this->get(route('invoices.document', $invoice))->assertOk()
            ->assertSee('FPR 1/26')->assertSee('Agenzia dimostrativa')->assertSee('Cliente dimostrativo')
            ->assertSee('Via di prova 1')->assertSee('Bonifico bancario')->assertSee('IBAN-DIMOSTRATIVO')
            ->assertSee('122,00 €')->assertSee('Stampa / Salva PDF')->assertSee('Scarica originale')
            ->assertDontSee('Nome modificato dopo importazione')->assertDontSee('Profilo modificato')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame($before, $snapshot());
        $this->assertSame($xml, Storage::disk('attachments')->get($invoice->attachments()->sole()->path));
        Http::assertNothingSent();
    }

    public function test_split_payment_preview_keeps_the_document_total_separate_from_the_net_amount(): void
    {
        $invoice = $this->import(InvoiceImportXml::make('FPA 1/26', split: true));
        $this->get(route('invoices.document', $invoice))->assertOk()->assertSee('Split payment')
            ->assertSee('Netto da pagare')->assertSee('122,00 €')->assertSee('100,00 €')
            ->assertViewHas('document', fn ($data) => $data['total'] === '122.00' && $data['payable'] === '100.00' && $data['split_tax'] === '22.00');
        $this->get(route('invoices.show', $invoice))->assertOk()->assertSee('Importata manualmente')
            ->assertSee(route('invoices.document', $invoice), false)->assertDontSee('Importata da documento esistente');
    }

    public function test_credit_note_has_its_own_printable_document_and_original_number(): void
    {
        $note = $this->import(InvoiceImportXml::make('FPR 17/26', 'TD04', description: 'Storno fattura 1/26'));
        $this->get(route('credit-notes.document', $note))->assertOk()->assertSee('Nota di credito')->assertSee('TD04')
            ->assertSee('FPR 17/26')->assertSee('Storno fattura 1/26')
            ->assertSee(route('credit-notes.show', $note), false);
        $this->get(route('credit-notes.show', $note))->assertOk()->assertSee('Visualizza nota di credito');
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('credit_note_allocations', 0);
    }

    public function test_multi_body_xml_uses_only_the_body_saved_for_the_selected_invoice(): void
    {
        $first = InvoiceImportXml::make(description: 'Prima fattura');
        $second = InvoiceImportXml::make('FPR 2/26', description: 'Seconda fattura');
        preg_match('/<FatturaElettronicaBody>.*<\/FatturaElettronicaBody>/s', $second, $body);
        $xml = str_replace('</p:FatturaElettronica>', $body[0].'</p:FatturaElettronica>', $first);
        $invoice = $this->import($xml, 1);
        $this->get(route('invoices.document', $invoice))->assertOk()->assertSee('FPR 2/26')->assertSee('Seconda fattura')
            ->assertDontSee('Prima fattura')->assertDontSee('FPR 1/26');
    }

    public function test_document_preview_escapes_untrusted_xml_text(): void
    {
        $payload = '<script>alert("documento")</script>';
        $invoice = $this->import(InvoiceImportXml::make(description: $payload));
        $this->get(route('invoices.document', $invoice))->assertOk()->assertSee($payload)->assertDontSee($payload, false);
        $this->get(route('invoices.show', $invoice))->assertOk()->assertSee($payload)->assertDontSee($payload, false);
    }

    public function test_guest_and_non_finance_users_cannot_open_either_document_preview(): void
    {
        $invoice = $this->import();
        $note = $this->import(InvoiceImportXml::make('FPR 17/26', 'TD04'));
        auth()->logout();
        $this->get(route('invoices.document', $invoice))->assertRedirect(route('login'));
        $this->get(route('credit-notes.document', $note))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => UserRole::Marketing, 'password_changed_at' => now()]));
        $this->get(route('invoices.document', $invoice))->assertForbidden();
        $this->get(route('credit-notes.document', $note))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => UserRole::Administration, 'password_changed_at' => now()]));
        $this->get(route('invoices.document', $invoice))->assertOk();
        $this->get(route('credit-notes.document', $note))->assertOk();
    }

    public function test_missing_or_unsafe_original_is_reported_without_losing_the_imported_record(): void
    {
        $invoice = $this->import();
        $attachment = $invoice->attachments()->sole();
        $original = Storage::disk('attachments')->get($attachment->path);
        $before = $invoice->fresh()->getRawOriginal();
        foreach (['<!DOCTYPE FatturaElettronica [<!ENTITY example "unsafe">]>'.$original,
            str_replace('FPR 1/26', 'FPR 99/26', $original), '<broken/>'] as $content) {
            Storage::disk('attachments')->put($attachment->path, $content);
            $this->get(route('invoices.document', $invoice))->assertRedirect(route('invoices.show', $invoice))->assertSessionHas('error');
        }
        Storage::disk('attachments')->delete($attachment->path);
        $this->get(route('invoices.document', $invoice))->assertRedirect(route('invoices.show', $invoice))->assertSessionHas('error');
        $this->assertSame($before, $invoice->fresh()->getRawOriginal());
        Http::assertNothingSent();
    }

    public function test_the_imported_document_route_does_not_render_an_unrelated_draft(): void
    {
        $invoice = Invoice::create(['number' => 'BOZZA-DEMO', 'client_id' => $this->client->id, 'created_by' => auth()->id(),
            'issue_date' => '2026-10-16', 'status' => 'draft', 'currency' => 'EUR', 'subtotal' => '100.00', 'tax_amount' => '22.00', 'total' => '122.00']);
        $this->get(route('invoices.document', $invoice))->assertNotFound();
    }
}
