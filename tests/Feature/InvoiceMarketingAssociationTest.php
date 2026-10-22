<?php

namespace Tests\Feature;

use App\Domain\Finance\Actions\RegisterPaymentAction;
use App\Enums\Finance\InvoiceFiscalStatus;
use App\Models\Client;
use App\Models\HostingService;
use App\Models\Invoice;
use App\Models\MarketingCampaign;
use App\Models\Project;
use App\Models\User;
use App\Services\FinanceReportQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InvoiceMarketingAssociationTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;
    private Invoice $invoice;
    private MarketingCampaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        Notification::fake();
        $user = User::factory()->create(['role' => 'developer', 'password_changed_at' => now()]);
        $user->forceFill(['admin_access' => true])->save();
        $this->actingAs($user);
        $this->client = Client::factory()->create();
        $project = Project::factory()->create(['client_id' => $this->client->id]);
        $this->campaign = MarketingCampaign::factory()->create(['client_id' => $this->client->id, 'name' => 'Marketing del cliente']);
        $this->invoice = Invoice::create([
            'number' => 'FPR TEST/26', 'client_id' => $this->client->id, 'project_id' => $project->id,
            'created_by' => $user->id, 'issue_date' => '2026-10-01', 'due_date' => '2026-10-11',
            'currency' => 'EUR', 'status' => 'issued', 'subtotal' => 100, 'tax_amount' => 22,
            'total' => 122, 'payable_total' => 122, 'paid_total' => 0,
            'fiscal_status' => InvoiceFiscalStatus::Imported, 'import_metadata' => ['number' => 'FPR TEST/26'],
        ]);
    }

    public function test_campaign_lookup_is_client_scoped_searchable_and_returns_only_identifiers_and_names(): void
    {
        MarketingCampaign::factory()->create(['name' => 'Marketing estraneo']);
        $this->getJson(route('finance.lookup', ['type' => 'campaigns', 'client_id' => $this->client->id, 'q' => 'del cliente']))
            ->assertOk()->assertExactJson([['id' => $this->campaign->id, 'name' => $this->campaign->name]]);
        $this->getJson(route('finance.lookup', ['type' => 'campaigns']))->assertOk()->assertExactJson([]);
        $this->getJson(route('finance.lookup', ['type' => 'campaigns', 'client_id' => $this->client->id, 'q' => '%']))
            ->assertOk()->assertExactJson([]);
    }

    public function test_marketing_assignment_preserves_fiscal_data_payments_and_client_totals(): void
    {
        app(RegisterPaymentAction::class)->execute(['invoice_id' => $this->invoice->id, 'amount' => 122,
            'payment_date' => '2026-10-11', 'method' => 'bank_transfer']);
        $before = $this->invoice->fresh()->only(['client_id', 'import_metadata', 'fiscal_status', 'subtotal', 'tax_amount', 'total', 'paid_total', 'status']);
        $totals = app(FinanceReportQuery::class)->totals(['client_id' => $this->client->id]);
        $this->get(route('invoices.associations.edit', $this->invoice))->assertOk()->assertSee('Progetto marketing esistente');
        $this->patch(route('invoices.associations.update', $this->invoice), [
            'assignment_type' => 'campaign', 'marketing_campaign_id' => $this->campaign->id,
        ])->assertSessionHasNoErrors()->assertRedirect(route('invoices.show', $this->invoice));
        $this->invoice->refresh();
        $this->assertSame($before, $this->invoice->only(array_keys($before)));
        $this->assertNull($this->invoice->project_id);
        $this->assertNull($this->invoice->hosting_service_id);
        $this->assertEquals($this->campaign->id, $this->invoice->marketing_campaign_id);
        $payment = $this->invoice->payments()->sole();
        $this->assertNull($payment->project_id);
        $this->assertSame('122.00', $payment->amount);
        $this->assertSame('2026-10-11', $payment->payment_date->toDateString());
        $this->assertSame($totals, app(FinanceReportQuery::class)->totals(['client_id' => $this->client->id]));
        $this->get(route('invoices.show', $this->invoice))->assertOk()->assertSee($this->campaign->name);
        $this->assertTrue($this->campaign->invoices()->whereKey($this->invoice->id)->exists());
    }

    public function test_marketing_assignment_rejects_other_clients_missing_and_unknown_campaigns(): void
    {
        $other = MarketingCampaign::factory()->create();
        $originalProject = $this->invoice->project_id;
        foreach ([null, $other->id, 999999] as $id) {
            $this->patch(route('invoices.associations.update', $this->invoice), [
                'assignment_type' => 'campaign', 'marketing_campaign_id' => $id,
            ])->assertSessionHasErrors('marketing_campaign_id');
        }
        $this->assertEquals($originalProject, $this->invoice->fresh()->project_id);
        $this->assertNull($this->invoice->marketing_campaign_id);
    }

    public function test_replacing_a_campaign_requires_confirmation_and_replaces_all_prior_destinations(): void
    {
        $this->invoice->update(['project_id' => null, 'marketing_campaign_id' => $this->campaign->id]);
        $replacement = MarketingCampaign::factory()->create(['client_id' => $this->client->id]);
        $payload = ['assignment_type' => 'campaign', 'marketing_campaign_id' => $replacement->id];
        $this->patch(route('invoices.associations.update', $this->invoice), $payload)->assertSessionHasErrors('replace_campaign');
        $this->assertEquals($this->campaign->id, $this->invoice->fresh()->marketing_campaign_id);
        $this->patch(route('invoices.associations.update', $this->invoice), $payload + ['replace_campaign' => true])->assertSessionHasNoErrors();
        $this->assertEquals($replacement->id, $this->invoice->fresh()->marketing_campaign_id);
        $this->patch(route('invoices.associations.update', $this->invoice), $payload)->assertSessionHasNoErrors();
        $service = HostingService::create(['client_id' => $this->client->id, 'name' => 'Hosting test', 'type' => 'hosting', 'status' => 'active']);
        $this->patch(route('invoices.associations.update', $this->invoice), ['assignment_type' => 'hosting', 'hosting_service_id' => $service->id])
            ->assertSessionHasErrors('replace_campaign');
        $this->patch(route('invoices.associations.update', $this->invoice), ['assignment_type' => 'hosting', 'hosting_service_id' => $service->id, 'replace_campaign' => true])
            ->assertSessionHasNoErrors();
        $this->assertNull($this->invoice->refresh()->marketing_campaign_id);
        $this->assertEquals($service->id, $this->invoice->hosting_service_id);
        $this->patch(route('invoices.associations.update', $this->invoice), $payload)->assertSessionHasNoErrors();
        $this->assertNull($this->invoice->fresh()->hosting_service_id);
    }

    public function test_payment_date_method_and_amount_can_be_corrected_after_full_settlement(): void
    {
        $payment = app(RegisterPaymentAction::class)->execute(['invoice_id' => $this->invoice->id, 'amount' => 122,
            'payment_date' => '2026-10-11', 'method' => 'bank_transfer']);
        $this->get(route('payments.show', $payment))->assertOk()->assertSee(route('payments.edit', $payment));
        $this->get(route('payments.edit', $payment))->assertOk()->assertSee('Data pagamento')->assertSee('Metodo');
        $this->patch(route('payments.update', $payment), ['amount' => 122, 'payment_date' => '2026-10-13', 'method' => 'card'])
            ->assertSessionHasNoErrors()->assertRedirect(route('invoices.show', $this->invoice));
        $this->assertSame('2026-10-13', $payment->refresh()->payment_date->toDateString());
        $this->assertSame('card', $payment->method);
        $this->assertSame('paid', $this->invoice->fresh()->status);
        $reports = app(FinanceReportQuery::class);
        $this->assertEquals(0, $reports->totals(['from' => '2026-10-11', 'to' => '2026-10-11'])['total_collected']);
        $this->assertEquals(122, $reports->totals(['from' => '2026-10-13', 'to' => '2026-10-13'])['total_collected']);
        $this->patch(route('payments.update', $payment), ['amount' => 60, 'payment_date' => '2026-10-13', 'method' => 'card'])
            ->assertSessionHasNoErrors();
        $this->assertSame('partially_paid', $this->invoice->refresh()->status);
        $this->assertEquals(62, $this->invoice->residual);
    }

    public function test_operational_accounts_cannot_search_or_assign_marketing_projects(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'developer', 'password_changed_at' => now()]));
        $this->getJson(route('finance.lookup', ['type' => 'campaigns', 'client_id' => $this->client->id]))->assertForbidden();
        $this->patch(route('invoices.associations.update', $this->invoice), [
            'assignment_type' => 'campaign', 'marketing_campaign_id' => $this->campaign->id,
        ])->assertForbidden();
        $this->assertNull($this->invoice->fresh()->marketing_campaign_id);
    }
}
