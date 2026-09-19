<?php

namespace Tests\Feature;

use App\Domain\Finance\Actions\StoreExpenseDocument;
use App\Enums\UserRole;
use App\Models\BillingProfile;
use App\Models\ExpenseDocument;
use App\Models\IntegrationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ArubaReceivedExpenseDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.aruba_einvoicing.enabled' => true,
            'services.aruba_einvoicing.environment' => 'demo',
            'services.aruba_einvoicing.username' => 'received-test-user',
            'services.aruba_einvoicing.password' => 'received-test-password',
            'services.aruba_einvoicing.allow_send' => false,
            'services.aruba_einvoicing.auth_base_url' => 'https://demoauth.fatturazioneelettronica.aruba.it',
            'services.aruba_einvoicing.api_base_url' => 'https://demows.fatturazioneelettronica.aruba.it',
        ]);
        Cache::flush();
        Storage::fake('attachments');
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin, 'status' => 'active', 'password_changed_at' => now()]));
        BillingProfile::create([
            'profile_key' => 'default', 'legal_name' => 'Agenzia di prova', 'vat_country_code' => 'IT', 'vat_number' => '01234567897',
            'fiscal_regime' => 'RF01', 'address' => 'Via di prova 1', 'postal_code' => '80100', 'city' => 'Napoli', 'province' => 'NA', 'country_code' => 'IT',
        ]);
    }

    public function test_search_uses_the_incoming_endpoint_filters_recipient_and_preserves_pagination(): void
    {
        $this->fakeAruba();
        $this->get(route('expenses.documents.aruba.search', ['from' => '2026-09-16', 'to' => '2026-09-17', 'page' => 2]))
            ->assertOk()->assertSee('FORNITORE TEST')->assertSee('TEST-001')->assertSee('Pagina precedente')->assertSee('Pagina successiva');
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/v2/invoices-in?')
                && $request['page'] === '2' && $request['size'] === '20'
                && $request['receiverCountry'] === 'IT' && $request['receiverVatcode'] === '01234567897'
                && str_starts_with($request['creationStartDate'], '2026-09-16T00:00:00')
                && str_starts_with($request['creationEndDate'], '2026-09-17T23:59:59');
        });
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/services/invoice/upload'));
        $this->assertDatabaseCount('expense_documents', 0);
    }

    public function test_search_rejects_large_intervals_before_contacting_aruba(): void
    {
        $this->get(route('expenses.documents.aruba.search', ['from' => '2026-09-01', 'to' => '2026-09-17']))->assertSessionHasErrors('to');
        Http::assertNothingSent();
    }

    public function test_import_stores_verified_xml_once_without_creating_or_paying_an_expense(): void
    {
        $xml = $this->xml();
        $this->fakeAruba($xml);
        $this->post(route('expenses.documents.aruba.import'), ['invoice_id' => 'incoming-1', 'body_index' => 0])->assertSessionHasNoErrors();
        $document = ExpenseDocument::sole();
        $this->assertSame('invoice', $document->kind);
        $this->assertSame('TEST-001', $document->number);
        $this->assertSame('122.00', $document->amount);
        $this->assertSame('2026-10-17', $document->due_date->toDateString());
        $this->assertSame('FORNITORE TEST', $document->issuer);
        $this->assertSame($xml, Storage::disk('attachments')->get($document->path));
        $this->post(route('expenses.documents.aruba.import'), ['invoice_id' => 'incoming-1', 'body_index' => 0])->assertRedirect(route('expenses.documents.show', $document));
        $this->assertDatabaseCount('expense_documents', 1);
        $this->assertDatabaseCount('expenses', 0);
        $this->assertCount(1, Http::recorded(fn ($request) => str_contains($request->url(), '/invoices-in/detail')));
        $response = IntegrationLog::where('event', 'received_invoice_detail')->sole()->response;
        foreach (['file', 'unsignedFile', 'pdfFile'] as $field) {
            $this->assertArrayNotHasKey($field, $response);
        }
        $this->assertStringNotContainsString(base64_encode($xml), IntegrationLog::all()->toJson());
        $this->assertStringNotContainsString('received-test-password', IntegrationLog::all()->toJson());
    }

    public function test_import_reuses_a_matching_manual_document_instead_of_duplicating_it(): void
    {
        $manual = app(StoreExpenseDocument::class)->execute([
            'kind' => 'invoice', 'issuer' => 'Fornitore Test', 'issuer_identifier' => '01879020517',
            'number' => 'TEST-001', 'document_date' => '2026-09-17', 'amount' => '122.00',
        ], '<prova />', 'manuale.xml');
        $this->fakeAruba();
        $this->post(route('expenses.documents.aruba.import'), ['invoice_id' => 'incoming-1', 'body_index' => 0])->assertRedirect(route('expenses.documents.show', $manual));
        $this->assertSame('incoming-1', $manual->fresh()->aruba_id);
        $this->assertDatabaseCount('expense_documents', 1);
        $this->assertCount(1, Storage::disk('attachments')->allFiles('expense-documents'));
    }

    public static function invalidDocuments(): array
    {
        return [['recipient'], ['currency'], ['credit'], ['negative'], ['invalid-xml'], ['external-entity'], ['missing-number'], ['wrong-id'], ['outgoing'], ['invalid-base64']];
    }

    #[DataProvider('invalidDocuments')]
    public function test_untrusted_or_incompatible_documents_are_rejected_without_storage(string $case): void
    {
        $xml = $this->xml();
        $payload = [];
        $xml = match ($case) {
            'recipient' => str_replace('01234567897', '99999999999', $xml),
            'currency' => str_replace('<Divisa>EUR</Divisa>', '<Divisa>USD</Divisa>', $xml),
            'credit' => str_replace('<TipoDocumento>TD01</TipoDocumento>', '<TipoDocumento>TD04</TipoDocumento>', $xml),
            'negative' => str_replace('<ImportoTotaleDocumento>122.00', '<ImportoTotaleDocumento>-122.00', $xml),
            'invalid-xml' => 'not xml',
            'external-entity' => '<?xml version="1.0"?><!DOCTYPE data [<!ENTITY ext SYSTEM "file:///not-readable">]><data>&ext;</data>',
            'missing-number' => str_replace('<Numero>TEST-001</Numero>', '', $xml),
            default => $xml,
        };
        if ($case === 'wrong-id') {
            $payload['id'] = 'another-id';
        }
        if ($case === 'outgoing') {
            $payload['docType'] = 'out';
        }
        if ($case === 'invalid-base64') {
            $payload['unsignedFile'] = 'not valid base64!';
        }
        $this->fakeAruba($xml, $payload);
        $this->from(route('expenses.documents.aruba'))->post(route('expenses.documents.aruba.import'), ['invoice_id' => 'incoming-1', 'body_index' => 0])
            ->assertRedirect(route('expenses.documents.aruba'))->assertSessionHasErrors('document');
        $this->assertDatabaseCount('expense_documents', 0);
        $this->assertCount(0, Storage::disk('attachments')->allFiles('expense-documents'));
    }

    public function test_batches_use_the_selected_body_and_reject_out_of_range_indices(): void
    {
        $xml = $this->xml();
        preg_match('/<FatturaElettronicaBody>.*<\/FatturaElettronicaBody>/s', $xml, $matches);
        $second = str_replace(['TEST-001', '122.00'], ['TEST-002', '50.00'], $matches[0]);
        $xml = str_replace('</FatturaElettronica>', $second.'</FatturaElettronica>', $xml);
        $this->fakeAruba($xml);
        $this->post(route('expenses.documents.aruba.import'), ['invoice_id' => 'incoming-1', 'body_index' => 1])->assertSessionHasNoErrors();
        $this->assertSame('TEST-002', ExpenseDocument::sole()->number);
        $this->assertSame('50.00', ExpenseDocument::sole()->amount);
        $this->post(route('expenses.documents.aruba.import'), ['invoice_id' => 'incoming-1', 'body_index' => 0])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('expense_documents', 2);
        $this->post(route('expenses.documents.aruba.import'), ['invoice_id' => 'incoming-1', 'body_index' => 9])->assertSessionHasErrors('document');
    }

    public function test_changed_billing_recipient_does_not_reuse_an_import_from_the_previous_recipient(): void
    {
        $this->fakeAruba();
        $this->post(route('expenses.documents.aruba.import'), ['invoice_id' => 'incoming-1', 'body_index' => 0])->assertSessionHasNoErrors();
        BillingProfile::current()->update(['vat_number' => '99999999999']);
        $this->post(route('expenses.documents.aruba.import'), ['invoice_id' => 'incoming-1', 'body_index' => 0])->assertSessionHasErrors('document');
        $this->assertDatabaseCount('expense_documents', 1);
    }

    public function test_disabled_connection_and_provider_failures_return_useful_errors(): void
    {
        config(['services.aruba_einvoicing.enabled' => false]);
        $this->from(route('expenses.documents.aruba'))->get(route('expenses.documents.aruba.search', ['from' => '2026-09-17', 'to' => '2026-09-17']))->assertSessionHasErrors('aruba');
        Http::assertNothingSent();
        config(['services.aruba_einvoicing.enabled' => true]);
        Http::fake([
            '*/auth/signin' => Http::response(['access_token' => 'test-access', 'expires_in' => 1800]),
            '*/api/v2/invoices-in/detail*' => Http::response(['message' => 'Unavailable'], 503),
        ]);
        $this->post(route('expenses.documents.aruba.import'), ['invoice_id' => 'incoming-1', 'body_index' => 0])->assertSessionHasErrors('aruba');
        $this->assertDatabaseCount('expense_documents', 0);
    }

    private function fakeAruba(?string $xml = null, array $overrides = []): void
    {
        $payload = array_replace([
            'id' => 'incoming-1', 'docType' => 'in', 'filename' => 'IT_TEST.xml',
            'sender' => ['description' => 'FORNITORE TEST', 'countryCode' => 'IT', 'vatCode' => '01879020517'],
            'invoices' => [['number' => 'TEST-001', 'invoiceDate' => '2026-09-17', 'totalDocument' => '122.00']],
            'unsignedFile' => base64_encode($xml ?? $this->xml()), 'file' => 'signed-placeholder', 'pdfFile' => 'pdf-placeholder',
        ], $overrides);
        Http::fake([
            '*/auth/signin' => Http::response(['access_token' => 'test-access', 'refresh_token' => 'test-refresh', 'expires_in' => 1800]),
            '*/api/v2/invoices-in/detail*' => Http::response($payload),
            '*/api/v2/invoices-in?*' => Http::response(['content' => [collect($payload)->except(['unsignedFile', 'file', 'pdfFile'])->all()], 'first' => false, 'last' => false, 'number' => 2, 'totalPages' => 3]),
        ]);
    }

    private function xml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<FatturaElettronica>
<FatturaElettronicaHeader>
<CedentePrestatore><DatiAnagrafici><IdFiscaleIVA><IdPaese>IT</IdPaese><IdCodice>01879020517</IdCodice></IdFiscaleIVA><Anagrafica><Denominazione>FORNITORE TEST</Denominazione></Anagrafica></DatiAnagrafici></CedentePrestatore>
<CessionarioCommittente><DatiAnagrafici><IdFiscaleIVA><IdPaese>IT</IdPaese><IdCodice>01234567897</IdCodice></IdFiscaleIVA></DatiAnagrafici></CessionarioCommittente>
</FatturaElettronicaHeader>
<FatturaElettronicaBody>
<DatiGenerali><DatiGeneraliDocumento><TipoDocumento>TD01</TipoDocumento><Divisa>EUR</Divisa><Data>2026-09-17</Data><Numero>TEST-001</Numero><ImportoTotaleDocumento>122.00</ImportoTotaleDocumento></DatiGeneraliDocumento></DatiGenerali>
<DatiPagamento><DettaglioPagamento><DataScadenzaPagamento>2026-10-17</DataScadenzaPagamento></DettaglioPagamento></DatiPagamento>
</FatturaElettronicaBody>
</FatturaElettronica>
XML;
    }
}
