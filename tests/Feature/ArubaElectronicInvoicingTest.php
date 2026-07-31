<?php

namespace Tests\Feature;

use App\Domain\Finance\Actions\PrepareElectronicInvoiceAction;
use App\Enums\Finance\ElectronicInvoiceTransmissionStatus;
use App\Enums\Finance\InvoiceFiscalStatus;
use App\Enums\UserRole;
use App\Models\BillingProfile;
use App\Models\Client;
use App\Models\ElectronicInvoiceEvent;
use App\Models\ElectronicInvoiceTransmission;
use App\Models\Invoice;
use App\Models\IntegrationLog;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ArubaElectronicInvoicingTest extends TestCase
{
    use RefreshDatabase;

    private const CALLBACK_KEY = 'callback-test-key-12345678901234567890';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureAruba();
        Cache::flush();
        $this->admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => 'active',
            'password_changed_at' => now(),
        ]);
    }

    public function test_dry_run_validates_the_exact_xml_without_sending_it_twice(): void
    {
        $invoice = $this->preparedInvoice();
        $this->fakeSuccessfulAruba();

        $this->actingAs($this->admin)
            ->post(route('invoices.fiscal.validate', $invoice))
            ->assertRedirect(route('invoices.show', $invoice))
            ->assertSessionHas('success');

        $this->post(route('invoices.fiscal.validate', $invoice))
            ->assertRedirect(route('invoices.show', $invoice));

        $transmission = $invoice->electronicInvoiceTransmissions()->sole();

        $this->assertSame('dry_run', $transmission->mode);
        $this->assertSame(
            ElectronicInvoiceTransmissionStatus::Validated,
            $transmission->status,
        );
        $this->assertSame(InvoiceFiscalStatus::Ready, $invoice->fresh()->fiscal_status);

        $uploads = Http::recorded(fn ($request) => str_ends_with(
            $request->url(),
            '/services/invoice/upload'
        ));

        $this->assertCount(1, $uploads);
        $request = $uploads->first()[0];
        $this->assertTrue($request['dryRun']);
        $this->assertStringContainsString(
            '<IdCodice>01879020517</IdCodice>',
            base64_decode($request['dataFile'], true),
        );
        $this->assertArrayNotHasKey(
            'dataFile',
            IntegrationLog::query()
                ->where('event', 'electronic_invoice_dry_run')
                ->firstOrFail()
                ->payload,
        );
    }

    public function test_live_send_requires_matching_validation_and_then_locks_the_operation(): void
    {
        $invoice = $this->preparedInvoice();
        $this->fakeSuccessfulAruba();

        $this->actingAs($this->admin)
            ->post(route('invoices.fiscal.send', $invoice))
            ->assertSessionHasErrors('fiscal');

        $this->assertDatabaseCount('electronic_invoice_transmissions', 0);
        Http::assertNothingSent();

        $this->post(route('invoices.fiscal.validate', $invoice))->assertSessionHasNoErrors();
        $this->post(route('invoices.fiscal.send', $invoice))->assertSessionHasNoErrors();

        $invoice->refresh();
        $live = $invoice->electronicInvoiceTransmissions()
            ->where('mode', 'live')
            ->sole();

        $this->assertSame(InvoiceFiscalStatus::Sent, $invoice->fiscal_status);
        $this->assertSame(
            ElectronicInvoiceTransmissionStatus::TakenCharge,
            $live->status,
        );
        $this->assertNotNull($live->upload_filename);
    }

    public function test_uncertain_live_response_is_not_retried_and_keeps_invoice_blocked(): void
    {
        $invoice = $this->preparedInvoice();
        $this->fakeSuccessfulAruba();
        $this->actingAs($this->admin)
            ->post(route('invoices.fiscal.validate', $invoice))
            ->assertSessionHasNoErrors();

        Http::fake([
            '*/services/invoice/upload' => Http::failedConnection('timeout'),
        ]);

        $this->post(route('invoices.fiscal.send', $invoice))
            ->assertSessionHasErrors('fiscal');

        $invoice->refresh();
        $live = $invoice->electronicInvoiceTransmissions()
            ->where('mode', 'live')
            ->sole();

        $this->assertSame(InvoiceFiscalStatus::Transmitting, $invoice->fiscal_status);
        $this->assertSame(
            ElectronicInvoiceTransmissionStatus::Uncertain,
            $live->status,
        );
        $this->assertFalse($this->admin->can('sendFiscal', $invoice));
    }

    public function test_callbacks_are_authenticated_deduplicated_and_update_the_invoice(): void
    {
        $invoice = $this->preparedInvoice();
        $transmission = $this->liveTransmission($invoice);
        $statusPayload = [
            'username' => 'demo-user',
            'countryCode' => 'IT',
            'vatCode' => '01234567897',
            'fiscalCode' => '01234567897',
            'invoiceFileName' => $transmission->upload_filename.'.p7m',
            'sdiIdentification' => 'SDI-12345',
            'status' => 'Inviata',
            'errorDescription' => null,
            'updateDate' => '2026-07-31T12:00:00+02:00',
        ];

        $this->postJson(
            route('api.v1.integrations.aruba.invoice-status.update'),
            $statusPayload,
        )->assertUnauthorized();

        $this->withHeader('Authorization', self::CALLBACK_KEY)
            ->postJson(
                route('api.v1.integrations.aruba.invoice-status.update'),
                $statusPayload,
            )
            ->assertOk()
            ->assertJson(['ok' => true]);

        $notificationPayload = [
            'username' => 'demo-user',
            'countryCode' => 'IT',
            'vatCode' => '01234567897',
            'fiscalCode' => '01234567897',
            'inOut' => 'OUT',
            'notifyType' => 'RC',
            'sdiIdentification' => 'SDI-12345',
            'notifyFileName' => 'IT01879020517_2026000001_RC_001.xml',
            'notifyXmlBase64' => base64_encode('<?xml version="1.0"?><RicevutaConsegna/>'),
            'notificationDate' => '2026-07-31T12:05:00+02:00',
        ];

        $this->withHeader('Authorization', 'Bearer '.self::CALLBACK_KEY)
            ->postJson(
                route('api.v1.integrations.aruba.notifications.store'),
                $notificationPayload,
            )
            ->assertOk();
        $this->withHeader('Authorization', self::CALLBACK_KEY)
            ->postJson(
                route('api.v1.integrations.aruba.notifications.store'),
                $notificationPayload,
            )
            ->assertOk();

        $invoice->refresh();
        $transmission->refresh();

        $this->assertSame(InvoiceFiscalStatus::Delivered, $invoice->fiscal_status);
        $this->assertSame(
            ElectronicInvoiceTransmissionStatus::Delivered,
            $transmission->status,
        );
        $this->assertSame('SDI-12345', $transmission->sdi_id);
        $this->assertSame(2, $transmission->events()->count());
        $this->assertSame(
            '<?xml version="1.0"?><RicevutaConsegna/>',
            ElectronicInvoiceEvent::query()
                ->where('type', 'notification')
                ->sole()
                ->document_content,
        );
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_manual_sync_reads_detail_and_sdi_notifications(): void
    {
        $invoice = $this->preparedInvoice();
        $transmission = $this->liveTransmission($invoice);

        Http::fake([
            '*/auth/signin' => Http::response([
                'access_token' => 'access-token',
                'refresh_token' => 'refresh-token',
                'expires_in' => 1800,
            ]),
            '*/api/v2/invoices-out/detail*' => Http::response([
                'id' => 'ARUBA-100',
                'filename' => $transmission->upload_filename,
                'idSdi' => 'SDI-100',
                'lastUpdate' => '2026-07-31T12:00:00+02:00',
                'invoices' => [[
                    'status' => 'Inviata',
                    'statusDescription' => '',
                ]],
            ]),
            '*/api/v2/invoices-out/notifications*' => Http::response([
                'count' => 1,
                'notifications' => [[
                    'date' => '2026-07-31T12:05:00+02:00',
                    'docType' => 'RC',
                    'file' => base64_encode('<RicevutaConsegna/>'),
                    'filename' => 'IT01879020517_2026000001_RC_001.xml',
                    'invoiceId' => 'ARUBA-100',
                    'notificationDate' => '2026-07-31T12:05:00+02:00',
                    'result' => '',
                ]],
            ]),
        ]);

        $this->actingAs($this->admin)
            ->post(route('invoices.fiscal.sync', $invoice))
            ->assertRedirect(route('invoices.show', $invoice))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            InvoiceFiscalStatus::Delivered,
            $invoice->fresh()->fiscal_status,
        );
        $this->assertSame('SDI-100', $transmission->fresh()->sdi_id);
        $this->assertSame(2, $transmission->events()->count());
    }

    private function configureAruba(): void
    {
        config([
            'app.url' => 'https://agency.example.test',
            'services.aruba_einvoicing.enabled' => true,
            'services.aruba_einvoicing.environment' => 'demo',
            'services.aruba_einvoicing.username' => 'demo-user',
            'services.aruba_einvoicing.password' => 'demo-password',
            'services.aruba_einvoicing.callback_key' => self::CALLBACK_KEY,
            'services.aruba_einvoicing.allow_send' => true,
            'services.aruba_einvoicing.require_dry_run' => true,
            'services.aruba_einvoicing.signature_domain' => '',
            'services.aruba_einvoicing.signature_credential' => '',
            'services.aruba_einvoicing.auth_base_url' => 'https://demoauth.fatturazioneelettronica.aruba.it',
            'services.aruba_einvoicing.api_base_url' => 'https://demows.fatturazioneelettronica.aruba.it',
            'services.aruba_einvoicing.connect_timeout' => 2,
            'services.aruba_einvoicing.timeout' => 5,
        ]);
    }

    private function fakeSuccessfulAruba(): void
    {
        Http::fake([
            '*/auth/signin' => Http::response([
                'access_token' => 'access-token',
                'refresh_token' => 'refresh-token',
                'expires_in' => 1800,
            ]),
            '*/services/invoice/upload' => Http::response([
                'errorCode' => '0000',
                'errorDescription' => 'Operazione completata - REQUEST123456',
                'uploadFileName' => 'IT01879020517_2026000001.xml',
            ]),
        ]);
    }

    private function preparedInvoice(): Invoice
    {
        BillingProfile::create([
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
        ]);
        $client = Client::create([
            'name' => 'Cliente Demo',
            'company_name' => 'Cliente Demo Srl',
            'slug' => 'cliente-demo-'.uniqid(),
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
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Progetto fiscale',
            'slug' => 'progetto-fiscale-'.uniqid(),
            'status' => 'active',
        ]);
        $invoice = Invoice::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'created_by' => $this->admin->id,
            'number' => 'BOZZA-'.uniqid(),
            'issue_date' => '2026-07-31',
            'due_date' => '2026-08-30',
            'status' => 'draft',
            'currency' => 'EUR',
            'subtotal' => 100,
            'tax_amount' => 22,
            'total' => 122,
            'paid_total' => 0,
        ]);
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

        app(PrepareElectronicInvoiceAction::class)->execute($invoice);

        return $invoice->fresh();
    }

    private function liveTransmission(Invoice $invoice): ElectronicInvoiceTransmission
    {
        $invoice->update(['fiscal_status' => InvoiceFiscalStatus::Sent]);

        return $invoice->electronicInvoiceTransmissions()->create([
            'submitted_by' => $this->admin->id,
            'provider' => 'aruba',
            'environment' => 'demo',
            'mode' => 'live',
            'attempt_number' => 1,
            'status' => ElectronicInvoiceTransmissionStatus::Sent,
            'xml_filename' => 'IT01879020517_2026000001.xml',
            'xml_hash' => hash('sha256', '<xml/>'),
            'xml_content' => '<xml/>',
            'upload_filename' => 'IT01879020517_2026000001.xml',
            'submitted_at' => now(),
        ]);
    }
}
