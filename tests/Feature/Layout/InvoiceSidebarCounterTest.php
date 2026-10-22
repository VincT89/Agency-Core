<?php

namespace Tests\Feature\Layout;

use App\Domain\Finance\Actions\RegisterPaymentAction;
use App\Enums\Finance\InvoiceFiscalStatus;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InvoiceSidebarCounterTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Bus::fake();
        Notification::fake();
        Cache::flush();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin, 'password_changed_at' => now()]));
        $this->client = Client::factory()->create();
    }

    private function invoice(array $attributes = []): Invoice
    {
        return Invoice::create(array_replace(['client_id' => $this->client->id, 'created_by' => auth()->id(),
            'number' => 'CONTATORE-'.(Invoice::count() + 1), 'issue_date' => today(), 'due_date' => today()->addMonth(),
            'currency' => 'EUR', 'subtotal' => '100.00', 'tax_amount' => '22.00', 'total' => '122.00',
            'payable_total' => '122.00', 'paid_total' => '0.00', 'credited_total' => '0.00', 'status' => 'issued',
            'fiscal_status' => InvoiceFiscalStatus::Imported], $attributes));
    }

    public function test_finance_roles_count_all_stored_invoices_regardless_of_status_or_list_filters(): void
    {
        $this->invoice();
        $this->invoice(['status' => 'partially_paid', 'paid_total' => '30.00']);
        $this->invoice(['status' => 'overdue', 'due_date' => today()->subDay()]);
        $this->invoice(['payable_total' => '100.00', 'paid_total' => '100.00']);
        $this->invoice(['credited_total' => '122.00']);
        foreach (['draft', 'paid', 'credited', 'cancelled'] as $status) $this->invoice(['status' => $status]);

        foreach ([UserRole::Admin, UserRole::Administration] as $role) {
            $user = User::factory()->create(['role' => $role, 'password_changed_at' => now()]);
            $this->actingAs($user)->get(route('invoices.index'))->assertOk()
                ->assertSee('title="Fatture presenti: 9"', false);
            $this->get(route('invoices.index', ['search' => 'nessun-documento']))->assertOk()
                ->assertSee('title="Fatture presenti: 9"', false);
        }
    }

    public function test_settling_an_invoice_keeps_the_counter_and_new_invoices_update_it_immediately(): void
    {
        $invoice = $this->invoice();
        $this->get(route('invoices.index'))->assertOk()->assertSee('title="Fatture presenti: 1"', false);
        $this->assertTrue(Cache::has('sidebar_counts_'.auth()->id()));
        app(RegisterPaymentAction::class)->execute(['invoice_id' => $invoice->id, 'amount' => '122.00',
            'method' => 'bank_transfer', 'payment_date' => today()->toDateString()]);
        $this->get(route('invoices.index'))->assertOk()->assertSee('title="Fatture presenti: 1"', false);
        $this->invoice();
        $this->get(route('invoices.index'))->assertOk()->assertSee('title="Fatture presenti: 2"', false);
        $this->assertTrue(Cache::has('sidebar_counts_'.auth()->id()));
    }

    public function test_operational_users_cannot_see_the_finance_counter(): void
    {
        $this->invoice();
        foreach ([UserRole::Developer, UserRole::Photographer, UserRole::Commercial] as $role) {
            $user = User::factory()->create(['role' => $role, 'password_changed_at' => now()]);
            $this->actingAs($user)->get(route('dashboard'))->assertOk()
                ->assertDontSee('title="Fatture presenti:', false);
            $this->get(route('invoices.index'))->assertForbidden();
        }
    }
}
