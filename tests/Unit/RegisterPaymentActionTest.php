<?php

namespace Tests\Unit;

use App\Domain\Finance\Actions\RegisterPaymentAction;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterPaymentActionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Client $client;
    private RegisterPaymentAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->user);

        $this->client = Client::create([
            'name'   => 'Cliente Test',
            'slug'   => 'cliente-test',
            'status' => 'active',
        ]);

        $this->action = new RegisterPaymentAction();
    }

    public function test_overdue_invoice_becomes_partially_paid_on_partial_payment(): void
    {
        $invoice = Invoice::create([
            'client_id'  => $this->client->id,
            'created_by' => $this->user->id,
            'number'     => 'INV-1',
            'issue_date' => now()->toDateString(),
            'due_date'   => now()->subDays(10)->toDateString(),
            'status'     => 'overdue',
            'currency'   => 'EUR',
            'subtotal'   => 100.00,
            'tax_amount' => 0.00,
            'total'      => 100.00,
            'paid_total' => 0.00,
        ]);

        $this->action->execute([
            'invoice_id'   => $invoice->id,
            'amount'       => 50.00,
            'payment_date' => now()->toDateString(),
            'method'       => 'bank_transfer',
        ]);

        $this->assertEquals('partially_paid', $invoice->fresh()->status);
    }
}
