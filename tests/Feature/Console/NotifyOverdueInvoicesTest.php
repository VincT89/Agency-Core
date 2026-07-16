<?php

namespace Tests\Feature\Console;

use App\Models\Invoice;
use App\Models\User;
use App\Models\Client;
use App\Models\Project;
use App\Enums\UserRole;
use App\Notifications\InvoiceOverdueNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotifyOverdueInvoicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function createDummyInvoice($overrides = [])
    {
        $client = Client::first() ?? Client::factory()->create();
        $project = Project::first() ?? Project::factory()->create(['client_id' => $client->id]);
        $user = User::first() ?? User::factory()->create();
        
        return Invoice::create(array_merge([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'created_by' => $user->id,
            'number' => 'INV-' . uniqid(),
            'issue_date' => today()->subDays(10)->format('Y-m-d'),
            'status' => 'draft',
            'currency' => 'EUR',
            'subtotal' => 100.00,
            'tax_amount' => 22.00,
            'paid_total' => 0.00,
            'due_date' => today()->addDays(5)->format('Y-m-d'),
        ], $overrides));
    }

    public function test_it_marks_issued_expired_invoices_as_overdue_and_notifies(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => 'active']);
        
        $invoice = $this->createDummyInvoice([
            'status' => 'issued',
            'due_date' => today()->subDay()->format('Y-m-d'), // expired
        ]);

        $this->artisan('notify:overdue-invoices')
            ->expectsOutputToContain('Inviate 1 notifiche')
            ->assertExitCode(0);

        $this->assertEquals('overdue', $invoice->refresh()->status);
        Notification::assertSentTo($admin, InvoiceOverdueNotification::class);
    }

    public function test_it_excludes_cancelled_invoices(): void
    {
        User::factory()->create(['role' => UserRole::Admin, 'status' => 'active']);
        
        $this->createDummyInvoice([
            'status' => 'cancelled',
            'due_date' => today()->subDay()->format('Y-m-d'),
        ]);

        $this->artisan('notify:overdue-invoices')->assertExitCode(0);
        Notification::assertNothingSent();
    }

    public function test_it_excludes_draft_invoices(): void
    {
        User::factory()->create(['role' => UserRole::Admin, 'status' => 'active']);
        
        $this->createDummyInvoice([
            'status' => 'draft',
            'due_date' => today()->subDay()->format('Y-m-d'),
        ]);

        $this->artisan('notify:overdue-invoices')->assertExitCode(0);
        Notification::assertNothingSent();
    }

    public function test_it_excludes_paid_invoices(): void
    {
        User::factory()->create(['role' => UserRole::Admin, 'status' => 'active']);
        
        $this->createDummyInvoice([
            'status' => 'paid',
            'due_date' => today()->subDay()->format('Y-m-d'),
        ]);

        $this->artisan('notify:overdue-invoices')->assertExitCode(0);
        Notification::assertNothingSent();
    }

    public function test_it_excludes_invoices_due_today(): void
    {
        User::factory()->create(['role' => UserRole::Admin, 'status' => 'active']);
        
        $invoice = $this->createDummyInvoice([
            'status' => 'issued',
            'due_date' => today()->format('Y-m-d'),
        ]);

        $this->artisan('notify:overdue-invoices')->assertExitCode(0);
        $this->assertEquals('issued', $invoice->refresh()->status);
        Notification::assertNothingSent();
    }

    public function test_it_ignores_partially_paid_invoices_for_now(): void
    {
        // Characterization test to ensure partially_paid is not affected by current logic
        User::factory()->create(['role' => UserRole::Admin, 'status' => 'active']);
        
        $invoice = $this->createDummyInvoice([
            'status' => 'partially_paid',
            'due_date' => today()->subDay()->format('Y-m-d'),
        ]);

        $this->artisan('notify:overdue-invoices')->assertExitCode(0);
        $this->assertEquals('partially_paid', $invoice->refresh()->status);
        Notification::assertNothingSent();
    }
}
