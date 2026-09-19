<?php

namespace Tests\Feature;

use App\Domain\Finance\Actions\ImportIssuedInvoice;
use App\Domain\Finance\Services\InvoiceImportFile;
use App\Domain\Finance\Services\InvoiceImportReader;
use App\Enums\Finance\InvoiceFiscalStatus;
use App\Enums\UserRole;
use App\Livewire\Invoices\InvoiceImport;
use App\Models\BillingProfile;
use App\Models\Client;
use App\Models\ExpenseDocument;
use App\Models\IntegrationLog;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Integrations\Aruba\ArubaInvoiceClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InvoiceImportTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        Cache::flush();
        Storage::fake('attachments');
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin, 'password_changed_at' => now()]));
        $this->client = Client::factory()->create(['name' => 'Cliente dimostrativo', 'vat_number' => '01879020517', 'tax_code' => null, 'country_code' => 'IT']);
        BillingProfile::create(['profile_key' => 'default', 'legal_name' => 'Agenzia dimostrativa',
            'vat_country_code' => 'IT', 'vat_number' => '01234567897', 'fiscal_regime' => 'RF01',
            'address' => 'Via di prova 1', 'postal_code' => '80100', 'city' => 'Napoli', 'province' => 'NA', 'country_code' => 'IT']);
        config(['services.aruba_einvoicing.enabled' => true, 'services.aruba_einvoicing.environment' => 'demo',
            'services.aruba_einvoicing.username' => 'import-test-user', 'services.aruba_einvoicing.password' => 'import-test-password',
            'services.aruba_einvoicing.allow_send' => false, 'services.aruba_einvoicing.auth_base_url' => 'https://demoauth.fatturazioneelettronica.aruba.it',
            'services.aruba_einvoicing.api_base_url' => 'https://demows.fatturazioneelettronica.aruba.it']);
    }

    private function xml(string $direction = 'out'): string
    {
        $agency = '<DatiAnagrafici><IdFiscaleIVA><IdPaese>IT</IdPaese><IdCodice>01234567897</IdCodice></IdFiscaleIVA><Anagrafica><Denominazione>Agenzia dimostrativa</Denominazione></Anagrafica></DatiAnagrafici>';
        $party = '<DatiAnagrafici><IdFiscaleIVA><IdPaese>IT</IdPaese><IdCodice>01879020517</IdCodice></IdFiscaleIVA><Anagrafica><Denominazione>Controparte dimostrativa</Denominazione></Anagrafica></DatiAnagrafici><Sede><Indirizzo>Via di prova</Indirizzo><NumeroCivico>7</NumeroCivico><CAP>80100</CAP><Comune>Napoli</Comune><Provincia>NA</Provincia><Nazione>IT</Nazione></Sede>';
        $sender = $direction === 'out' ? $agency : $party;
        $receiver = $direction === 'out' ? $party : $agency;

        return '<?xml version="1.0" encoding="UTF-8"?><p:FatturaElettronica xmlns:p="http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2" versione="FPR12"><FatturaElettronicaHeader><CedentePrestatore>'.$sender.'</CedentePrestatore><CessionarioCommittente>'.$receiver.'</CessionarioCommittente></FatturaElettronicaHeader>'.<<<'XML'
<FatturaElettronicaBody><DatiGenerali><DatiGeneraliDocumento><TipoDocumento>TD01</TipoDocumento><Divisa>EUR</Divisa><Data>2026-09-17</Data><Numero>DEMO-001</Numero><ImportoTotaleDocumento>122.00</ImportoTotaleDocumento></DatiGeneraliDocumento></DatiGenerali>
<DatiBeniServizi><DettaglioLinee><NumeroLinea>1</NumeroLinea><Descrizione>Servizio dimostrativo &amp; collaudo</Descrizione><Quantita>2.00000000</Quantita><PrezzoUnitario>50.00000000</PrezzoUnitario><PrezzoTotale>100.00</PrezzoTotale><AliquotaIVA>22.00</AliquotaIVA></DettaglioLinee><DatiRiepilogo><AliquotaIVA>22.00</AliquotaIVA><ImponibileImporto>100.00</ImponibileImporto><Imposta>22.00</Imposta></DatiRiepilogo></DatiBeniServizi>
<DatiPagamento><DettaglioPagamento><DataScadenzaPagamento>2026-10-17</DataScadenzaPagamento><ImportoPagamento>122.00</ImportoPagamento></DettaglioPagamento></DatiPagamento></FatturaElettronicaBody></p:FatturaElettronica>
XML;
    }

    private function preview(?string $xml = null, string $direction = 'out')
    {
        return Livewire::test(InvoiceImport::class)->set('direction', $direction)
            ->set('file', UploadedFile::fake()->createWithContent('documento.xml', $xml ?? $this->xml($direction)))
            ->call('previewFile')->assertHasNoErrors();
    }

    public function test_outgoing_xml_requires_confirmation_and_preserves_original_amounts_and_file_without_sending(): void
    {
        $this->get(route('invoices.import'))->assertOk()->assertSee('Aruba tramite API');
        $component = $this->preview()->assertSet('clientId', $this->client->id)->assertSet('dueDate', '2026-10-17');
        $component->call('import')->assertHasErrors(['confirmed']);
        $this->assertDatabaseCount('invoices', 0);
        $component->set('confirmed', true)->call('import')->assertHasNoErrors();
        $invoice = Invoice::sole();
        $component->assertRedirect(route('invoices.show', $invoice));
        $this->assertSame(InvoiceFiscalStatus::Imported, $invoice->fiscal_status);
        $this->assertSame('122.00', $invoice->total);
        $this->assertSame('100.00', $invoice->subtotal);
        $this->assertSame('22.00', $invoice->tax_amount);
        $this->assertSame('0.00', $invoice->paid_total);
        $this->assertSame('2.00000000', $invoice->import_metadata['items'][0]['quantity']);
        $this->assertNull($invoice->fiscal_number);
        $this->assertNull($invoice->fiscal_snapshot);
        $attachment = $invoice->attachments()->sole();
        $this->assertSame($this->xml(), Storage::disk('attachments')->get($attachment->path));
        foreach (['update', 'delete', 'prepareFiscal', 'sendFiscal', 'reopenFiscal', 'syncFiscal'] as $ability) {
            $this->assertFalse(Gate::allows($ability, $invoice), $ability);
        }
        $this->get(route('invoices.show', $invoice))->assertOk()->assertSee('DEMO-001')->assertSee('Servizio dimostrativo')->assertDontSee('Invia ad Aruba e allo SdI');
        $this->get(route('invoices.index', ['client_id' => $this->client->id]))->assertOk()->assertSee($invoice->number);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('electronic_invoice_transmissions', 0);
        Http::assertNothingSent();
    }

    public function test_duplicate_import_does_not_duplicate_invoice_file_or_overwrite_payments(): void
    {
        $this->preview()->set('confirmed', true)->call('import')->assertHasNoErrors();
        Invoice::sole()->update(['paid_total' => '10.00', 'status' => 'partially_paid']);
        $this->preview()->set('confirmed', true)->call('import')->assertHasNoErrors();
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('attachments', 1);
        $this->assertSame('10.00', Invoice::sole()->paid_total);
        $this->assertCount(1, Storage::disk('attachments')->allFiles());
        $conflict = str_replace(['<ImponibileImporto>100.00', '<Imposta>22.00'], ['<ImponibileImporto>101.00', '<Imposta>21.00'], $this->xml());
        $this->preview($conflict)->set('confirmed', true)->call('import')->assertHasErrors('document');
        $this->assertSame('100.00', Invoice::sole()->subtotal);
    }

    public function test_imported_invoice_can_receive_and_correct_payments_without_unlocking_fiscal_data(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Administration, 'password_changed_at' => now()]));
        $this->preview()->set('confirmed', true)->call('import')->assertHasNoErrors();
        $invoice = Invoice::sole();
        $data = ['invoice_id' => $invoice->id, 'payment_date' => '2026-09-19', 'amount' => '50.00', 'method' => 'bank_transfer'];
        $this->post(route('payments.store'), $data)->assertRedirect(route('invoices.show', $invoice))->assertSessionHasNoErrors();
        $this->assertSame('50.00', $invoice->fresh()->paid_total);
        $this->assertSame('partially_paid', $invoice->fresh()->status);
        $this->post(route('payments.store'), array_replace($data, ['amount' => '100.00']))->assertSessionHasErrors('amount');
        $payment = $invoice->payments()->sole();
        $this->put(route('payments.update', $payment), array_replace($data, ['amount' => '60.00']))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('60.00', $invoice->fresh()->paid_total);
        $this->assertFalse(Gate::allows('update', $invoice));
        $this->assertFalse(Gate::allows('sendFiscal', $invoice));
        $this->delete(route('payments.destroy', $payment))->assertRedirect();
        $this->assertSame('0.00', $invoice->fresh()->paid_total);
        $this->actingAs(User::factory()->create(['role' => UserRole::Commercial]));
        $this->post(route('payments.store'), $data)->assertForbidden();
    }

    public function test_client_matching_prevents_wrong_clients_and_duplicate_anagraphics(): void
    {
        $wrong = Client::factory()->create(['vat_number' => '99999999999', 'country_code' => 'IT']);
        $this->preview()->set('clientId', $wrong->id)->set('confirmed', true)->call('import')->assertHasErrors('clientId');
        $this->preview()->set('createClient', true)->set('confirmed', true)->call('import')->assertHasErrors('clientId');
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('clients', 2);
    }

    public function test_unknown_xml_client_can_be_created_only_on_final_confirmation(): void
    {
        $xml = str_replace('01879020517', '12345678903', $this->xml());
        $component = $this->preview($xml)->set('createClient', true);
        $this->assertDatabaseCount('clients', 1);
        $component->set('confirmed', true)->call('import')->assertHasNoErrors();
        $newClient = Invoice::sole()->client;
        $this->assertSame('12345678903', $newClient->vat_number);
        $this->assertSame('Via di prova 7', $newClient->address);
        $this->assertSame('Cliente dimostrativo', $this->client->fresh()->name);
    }

    public function test_existing_normal_invoice_is_reused_by_original_number(): void
    {
        $invoice = Invoice::create(['client_id' => $this->client->id, 'created_by' => auth()->id(), 'number' => 'INTERNO-01', 'fiscal_number' => 'DEMO-001',
            'issue_date' => '2026-09-17', 'status' => 'paid', 'currency' => 'EUR', 'subtotal' => 100, 'tax_amount' => 22, 'total' => 122, 'paid_total' => 122]);
        $this->preview()->set('confirmed', true)->call('import')->assertRedirect(route('invoices.show', $invoice))->assertHasNoErrors();
        $this->assertDatabaseCount('invoices', 1);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_same_number_in_another_year_is_distinct(): void
    {
        $this->preview()->set('confirmed', true)->call('import')->assertHasNoErrors();
        $this->preview(str_replace('2026-', '2025-', $this->xml()))->set('confirmed', true)->call('import')->assertHasNoErrors();
        $this->assertDatabaseCount('invoices', 2);
    }

    public function test_same_original_number_with_a_different_date_or_document_type_cannot_be_imported_twice(): void
    {
        $this->preview()->set('confirmed', true)->call('import')->assertHasNoErrors();
        $this->preview(str_replace('TD01', 'TD06', $this->xml()))->set('confirmed', true)->call('import')->assertHasErrors('document');
        $this->preview(str_replace('2026-09-17', '2026-09-18', $this->xml()))->set('confirmed', true)->call('import')->assertHasErrors('document');
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_existing_fiscal_number_on_a_different_date_is_reported_as_a_conflict(): void
    {
        Invoice::create(['client_id' => $this->client->id, 'created_by' => auth()->id(), 'number' => 'INTERNAL-01', 'fiscal_number' => 'DEMO-001',
            'issue_date' => '2026-09-16', 'status' => 'issued', 'currency' => 'EUR', 'subtotal' => 100, 'tax_amount' => 22, 'total' => 122, 'paid_total' => 0]);
        $this->preview()->set('confirmed', true)->call('import')->assertHasErrors('document');
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_incoming_xml_creates_only_a_document_that_can_be_linked_to_an_expense(): void
    {
        $this->preview(direction: 'in')->set('confirmed', true)->call('import')->assertHasNoErrors();
        $document = ExpenseDocument::sole();
        $this->assertSame('122.00', $document->amount);
        $this->assertSame('Controparte dimostrativa', $document->issuer);
        $this->assertSame('2026-10-17', $document->due_date->toDateString());
        $this->assertSame($this->xml('in'), Storage::disk('attachments')->get($document->path));
        $this->get(route('expenses.documents.show', $document))->assertOk();
        $this->preview(direction: 'in')->set('confirmed', true)->call('import')->assertHasNoErrors();
        $this->assertDatabaseCount('expense_documents', 1);
        $this->assertDatabaseCount('expenses', 0);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_pdf_needs_explicit_manual_data_and_the_selected_client_cannot_change_after_review(): void
    {
        $component = Livewire::test(InvoiceImport::class)
            ->set('file', UploadedFile::fake()->createWithContent('fattura.pdf', "%PDF-1.7\nDocumento dimostrativo"))->call('previewFile')->assertHasNoErrors()
            ->assertSet('pdfForm', true)->set('confirmed', true)->call('import')->assertHasErrors()
            ->set('manual', ['number' => 'PDF-1', 'issue_date' => '2026-09-17', 'subtotal' => '100', 'tax_amount' => '22', 'total' => '122', 'country' => 'IT'])
            ->set('clientId', $this->client->id)->call('reviewPdf')->assertHasNoErrors()->assertSet('pdfForm', false)
            ->assertDontSee('Crea una nuova anagrafica con i dati del destinatario');
        $other = Client::factory()->create();
        $component->set('clientId', $other->id)->set('confirmed', true)->call('import')->assertHasErrors('clientId');
        $component->call('editPdf')->assertSet('pdfForm', true)->set('clientId', $this->client->id)
            ->call('reviewPdf')->set('confirmed', true)->call('import')->assertHasNoErrors();
        $this->assertSame('pdf', Invoice::sole()->import_metadata['format']);
    }

    public function test_pdf_incoming_requires_supplier_identity_and_preserves_due_date_selected_by_user(): void
    {
        Livewire::test(InvoiceImport::class)->set('direction', 'in')
            ->set('file', UploadedFile::fake()->createWithContent('fornitore.pdf', "%PDF-1.7\nDocumento dimostrativo"))->call('previewFile')
            ->call('reviewPdf')->assertHasErrors(['manual.issuer', 'manual.identifier'])
            ->set('manual', ['number' => 'PDF-IN-1', 'issue_date' => '2026-09-17', 'total' => '48.80', 'country' => 'IT', 'issuer' => 'Fornitore dimostrativo', 'identifier' => '01879020517'])
            ->call('reviewPdf')->assertHasNoErrors()->set('dueDate', '2026-11-30')->set('confirmed', true)->call('import')->assertHasNoErrors();
        $this->assertSame('48.80', ExpenseDocument::sole()->amount);
        $this->assertSame('2026-11-30', ExpenseDocument::sole()->due_date->toDateString());
    }

    public function test_binary_pdf_survives_a_database_cache_and_manual_review_without_corruption(): void
    {
        config(['cache.default' => 'database']);
        $pdf = "%PDF-1.7\n\xFF\xFE\x00\xA0\nDimostrativo";
        $component = Livewire::test(InvoiceImport::class)
            ->set('file', UploadedFile::fake()->createWithContent('binario.pdf', $pdf))->call('previewFile')->assertHasNoErrors();
        $cached = Cache::get('invoice-import:'.auth()->id().':'.$component->get('sourceToken'));
        $this->assertTrue(mb_check_encoding(serialize($cached), 'UTF-8'));
        $this->assertSame($pdf, base64_decode($cached['content'], true));
        $component->set('manual', ['number' => 'PDF-BINARY', 'issue_date' => '2026-09-17', 'subtotal' => '100', 'tax_amount' => '22', 'total' => '122', 'country' => 'IT'])
            ->set('clientId', $this->client->id)->call('reviewPdf')->assertHasNoErrors()->call('editPdf')->call('reviewPdf')->assertHasNoErrors()
            ->set('confirmed', true)->call('import')->assertHasNoErrors();
        $this->assertSame($pdf, Storage::disk('attachments')->get(Invoice::sole()->attachments()->sole()->path));
    }

    public function test_multi_document_xml_requires_selection_and_does_not_invent_a_due_date_for_multiple_installments(): void
    {
        $xml = $this->xml();
        preg_match('#<FatturaElettronicaBody>.*?</FatturaElettronicaBody>#s', $xml, $match);
        $second = str_replace('DEMO-001', 'DEMO-002', $match[0]);
        $second = str_replace('</DatiPagamento>', '<DettaglioPagamento><DataScadenzaPagamento>2026-11-17</DataScadenzaPagamento><ImportoPagamento>61.00</ImportoPagamento></DettaglioPagamento></DatiPagamento>', $second);
        $xml = str_replace('</p:FatturaElettronica>', $second.'</p:FatturaElettronica>', $xml);
        $this->preview($xml)->set('bodyIndex', 1)->assertSet('dueDate', '')->assertSee('più rate')
            ->set('confirmed', true)->call('import')->assertHasNoErrors();
        $this->assertSame('DEMO-002', Invoice::sole()->import_metadata['number']);
        $this->assertNull(Invoice::sole()->due_date);
        $this->assertCount(2, Invoice::sole()->import_metadata['payments']);
    }

    public function test_expired_preview_and_changed_agency_block_import(): void
    {
        $component = $this->preview();
        $this->travel(21)->minutes();
        $component->set('confirmed', true)->call('import')->assertHasErrors('document');
        $this->travelBack();
        $component = $this->preview();
        BillingProfile::current()->update(['vat_number' => '99999999999']);
        $component->set('confirmed', true)->call('import')->assertHasErrors('document');
        $this->assertDatabaseCount('invoices', 0);
    }

    public static function unsafeDocuments(): array
    {
        return [
            'wrong agency' => ['01234567897', '99999999999'],
            'credit note' => ['TD01', 'TD04'],
            'foreign currency' => ['EUR', 'USD'],
            'invalid date' => ['2026-09-17', '2026-02-31'],
            'invalid amount' => ['<Imposta>22.00', '<Imposta>text'],
            'split payment' => ['</DatiRiepilogo>', '<EsigibilitaIVA>S</EsigibilitaIVA></DatiRiepilogo>'],
            'withholding' => ['</DatiGeneraliDocumento>', '<DatiRitenuta/></DatiGeneraliDocumento>'],
            'doctype' => ['<p:FatturaElettronica ', '<!DOCTYPE test [<!ENTITY xxe SYSTEM "file:///not-readable">]><p:FatturaElettronica '],
        ];
    }

    #[DataProvider('unsafeDocuments')]
    public function test_invalid_or_unsupported_xml_is_rejected_before_any_write(string $find, string $replace): void
    {
        Livewire::test(InvoiceImport::class)->set('file', UploadedFile::fake()->createWithContent('fattura.xml', str_replace($find, $replace, $this->xml())))
            ->call('previewFile')->assertHasErrors('document');
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('expense_documents', 0);
        $this->assertCount(0, Storage::disk('attachments')->allFiles());
        Http::assertNothingSent();
    }

    public static function accessRoles(): array
    {
        return [[UserRole::Admin, true], [UserRole::Administration, true], [UserRole::Commercial, false], [UserRole::Developer, false], [UserRole::Marketing, false], [UserRole::Photographer, false]];
    }

    #[DataProvider('accessRoles')]
    public function test_only_finance_roles_can_access_import(UserRole $role, bool $allowed): void
    {
        $this->actingAs(User::factory()->create(['role' => $role, 'password_changed_at' => now()]));
        $this->get(route('invoices.import'))->assertStatus($allowed ? 200 : 403);
        if ($allowed) {
            $this->preview()->set('confirmed', true)->call('import')->assertHasNoErrors();
        } else {
            Livewire::test(InvoiceImport::class)->assertForbidden();
        }
    }

    private function fakeAruba(string $direction = 'out', array $overrides = []): array
    {
        $payload = array_replace(['id' => 'import-1', 'docType' => $direction, 'filename' => 'IT_DEMO.xml',
            'sender' => ['description' => 'Fornitore dimostrativo'], 'receiver' => ['description' => 'Cliente dimostrativo'],
            'invoices' => [['number' => 'DEMO-001', 'invoiceDate' => '2026-09-17', 'totalDocument' => '122.00', 'status' => 'Consegnata']],
            'file' => base64_encode($this->xml($direction))], $overrides);
        Http::fake([
            '*/auth/signin' => Http::response(['access_token' => 'import-test-token', 'expires_in' => 1800]),
            '*/api/v2/invoices-'.$direction.'/detail*' => Http::response($payload),
            '*/api/v2/invoices-'.$direction.'?*' => Http::response(['content' => [collect($payload)->except('file', 'unsignedFile')->all()], 'last' => false]),
        ]);

        return $payload;
    }

    private function arubaPreview(string $direction = 'out')
    {
        return Livewire::test(InvoiceImport::class)->set('source', 'aruba')->set('direction', $direction)
            ->set('from', '2026-09-16')->set('to', '2026-09-17')->call('searchAruba', 2)->assertHasNoErrors()
            ->assertSee('Pagina precedente')->assertSee('Pagina successiva')->call('previewAruba', 0)->assertHasNoErrors();
    }

    public function test_aruba_issued_import_reads_the_document_and_never_calls_upload(): void
    {
        $this->fakeAruba();
        $this->arubaPreview()->set('confirmed', true)->call('import')->assertHasNoErrors();
        $invoice = Invoice::sole();
        $this->assertSame('aruba', $invoice->import_source);
        $this->assertSame('import-1', $invoice->import_metadata['aruba_id']);
        $this->assertSame('demo', $invoice->import_metadata['environment']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v2/invoices-out?') && $request['page'] === '2' && $request['senderVatcode'] === '01234567897' && $request['senderCountry'] === 'IT');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/invoices-out/detail') && $request['includeFile'] === 'true' && $request['id'] === 'import-1' && ! isset($request['includeUnsignedFile']));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/upload'));
        $this->assertCount(2, Http::recorded(fn ($request) => str_contains($request->url(), '/invoices-out/detail')));
        $logs = IntegrationLog::all()->toJson();
        $this->assertStringNotContainsString(base64_encode($this->xml()), $logs);
        $this->assertStringNotContainsString('import-test-password', $logs);
        $this->assertDatabaseCount('electronic_invoice_transmissions', 0);
    }

    public function test_aruba_received_import_uses_unsigned_file_and_reuses_local_document(): void
    {
        $this->preview(direction: 'in')->set('confirmed', true)->call('import')->assertHasNoErrors();
        $this->fakeAruba('in', ['filename' => 'IT_DEMO.xml.p7m', 'unsignedFile' => base64_encode($this->xml('in')), 'file' => 'signed-file']);
        $this->arubaPreview('in')->set('confirmed', true)->call('import')->assertHasNoErrors();
        $this->assertDatabaseCount('expense_documents', 1);
        $this->assertSame('import-1', ExpenseDocument::sole()->aruba_id);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/invoices-in?') && $request['receiverVatcode'] === '01234567897');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/invoices-in/detail') && $request['includeUnsignedFile'] === 'true');
        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_aruba_does_not_accept_rejected_documents_or_wrong_detail_response(): void
    {
        $this->fakeAruba(overrides: ['invoices' => [['number' => 'DEMO-001', 'status' => 'Scartata']]]);
        $this->arubaPreview()->set('confirmed', true)->call('import')->assertHasErrors('document');
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_aruba_errors_and_long_intervals_do_not_create_records(): void
    {
        Livewire::test(InvoiceImport::class)->set('source', 'aruba')->set('from', '2026-09-01')->set('to', '2026-09-17')
            ->call('searchAruba')->assertHasErrors('to');
        Http::assertNothingSent();
        Http::fake(['*/auth/signin' => Http::response(['access_token' => 'test-token', 'expires_in' => 1800]), '*' => Http::response([], 503)]);
        Livewire::test(InvoiceImport::class)->set('source', 'aruba')->call('searchAruba')->assertHasErrors('aruba');
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_aruba_detail_must_match_selected_id_and_direction(): void
    {
        $payload = $this->fakeAruba();
        $component = Livewire::test(InvoiceImport::class)->set('source', 'aruba')->call('searchAruba')->assertHasNoErrors();
        // A mismatched detail must be rejected even if its XML would otherwise be valid.
        $this->mock(ArubaInvoiceClient::class)->shouldReceive('issuedInvoiceFile')->with('import-1')->once()->andReturn(array_replace($payload, ['id' => 'different-id']));
        $component->call('previewAruba', 0)->assertHasErrors('aruba');
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_changed_aruba_xml_is_rejected_after_preview(): void
    {
        $payload = $this->fakeAruba();
        $component = $this->arubaPreview();
        $this->mock(ArubaInvoiceClient::class)->shouldReceive('issuedInvoiceFile')->with('import-1')->once()
            ->andReturn(array_replace($payload, ['file' => base64_encode(str_replace('DEMO-001', 'DEMO-002', $this->xml()))]));
        $component->set('confirmed', true)->call('import')->assertHasErrors('document');
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_storage_failure_rolls_back_new_client_and_invoice(): void
    {
        $xml = str_replace('01879020517', '12345678903', $this->xml());
        $document = app(InvoiceImportReader::class)->read($xml, 'out')[0];
        Storage::shouldReceive('disk')->with('attachments')->andReturnSelf();
        Storage::shouldReceive('put')->once()->andReturn(false);
        Storage::shouldReceive('delete')->once()->andReturn(true);
        try {
            app(ImportIssuedInvoice::class)->execute($document, ['source' => 'file', 'format' => 'xml', 'filename' => 'test.xml', 'content' => $xml], null, true, null);
            $this->fail('Expected storage error');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Impossibile salvare il documento originale.', $exception->getMessage());
        }
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('clients', 1);
    }

    public function test_signed_p7m_is_extracted_and_imported_without_treating_it_as_plain_xml(): void
    {
        $paths = [];
        try {
            foreach (['config', 'input', 'signed'] as $key) {
                $paths[$key] = tempnam(sys_get_temp_dir(), 'invoice-test-');
            }
            file_put_contents($paths['config'], "[req]\ndistinguished_name=dn\n[dn]\n");
            file_put_contents($paths['input'], $this->xml());
            $options = ['config' => $paths['config'], 'private_key_bits' => 2048, 'digest_alg' => 'sha256'];
            $key = openssl_pkey_new($options);
            $csr = openssl_csr_new(['commonName' => 'Invoice import test only'], $key, $options);
            $certificate = openssl_csr_sign($csr, null, $key, 1, $options);
            $this->assertTrue(openssl_cms_sign($paths['input'], $paths['signed'], $certificate, $key, [], OPENSSL_CMS_BINARY, OPENSSL_ENCODING_DER));
            $signed = file_get_contents($paths['signed']);
            $this->assertSame($this->xml(), app(InvoiceImportFile::class)->xml($signed, 'p7m'));
            Livewire::test(InvoiceImport::class)->set('file', UploadedFile::fake()->createWithContent('fattura.xml.p7m', $signed))
                ->call('previewFile')->assertHasNoErrors()->set('confirmed', true)->call('import')->assertHasNoErrors();
            $this->assertSame('p7m', Invoice::sole()->attachments()->sole()->extension);
        } finally {
            foreach ($paths as $path) {
                if ($path && is_file($path)) {
                    unlink($path);
                }
            }
        }
    }
}
