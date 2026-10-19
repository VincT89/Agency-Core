<?php

namespace Tests\Feature\Core;

use App\Domain\Finance\Events\PaymentRecorded;
use App\Domain\Finance\Listeners\SendPaymentRecordedNotification;
use App\Domain\Shooting\Actions\CreateShootRequestAction;
use App\Domain\Shooting\Actions\PhotographerRespondAction;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\InvoiceOverdueNotification;
use App\Notifications\PaymentRecordedNotification;
use App\Notifications\ShootingWorkflowNotification;
use App\Notifications\TaskCreatedAdminNotification;
use App\Services\Tickets\TicketNotificationRecipientResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DeveloperAdminNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $developer;
    private User $admin;
    private User $administration;
    private User $normal;
    private User $inactive;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->developer = User::factory()->create(['role' => UserRole::Developer, 'status' => 'active']);
        $this->inactive = User::factory()->create(['role' => UserRole::Developer, 'status' => 'inactive']);
        DB::table('users')->whereIn('id', [$this->developer->id, $this->inactive->id])->update(['admin_access' => true]);
        $this->developer->refresh();
        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'status' => 'active']);
        $this->administration = User::factory()->create(['role' => UserRole::Administration, 'status' => 'active']);
        $this->normal = User::factory()->create(['role' => UserRole::Developer, 'status' => 'active']);
    }

    public function test_ticket_notifications_include_enabled_developer_once_and_exclude_inactive_accounts(): void
    {
        $resolver = app(TicketNotificationRecipientResolver::class);
        $this->assertEqualsCanonicalizing([$this->admin->id, $this->developer->id], $resolver->admins()->modelKeys());
        $ticket = new Ticket(['type' => 'quote']);
        $ticket->setRelation('assignee', $this->developer);
        $this->assertEqualsCanonicalizing([$this->admin->id, $this->developer->id], $resolver->recipientsFor($ticket)->modelKeys());
        $this->assertEqualsCanonicalizing(
            [$this->admin->id, $this->developer->id, $this->administration->id],
            $resolver->intakeRecipients($ticket)->modelKeys()
        );
        $this->actingAs($this->developer);
        $this->assertSame([$this->admin->id], $resolver->recipientsFor($ticket)->modelKeys());
    }

    public function test_task_admin_notifications_include_developer_but_do_not_duplicate_creator_or_assignee(): void
    {
        Task::factory()->create(['created_by' => $this->normal->id, 'assigned_to' => null]);
        Notification::assertSentToTimes($this->developer, TaskCreatedAdminNotification::class, 1);
        Notification::assertSentToTimes($this->admin, TaskCreatedAdminNotification::class, 1);
        Notification::assertNotSentTo([$this->normal, $this->inactive], TaskCreatedAdminNotification::class);

        Notification::fake();
        Task::factory()->create(['created_by' => $this->developer->id, 'assigned_to' => null]);
        Task::factory()->create(['created_by' => $this->normal->id, 'assigned_to' => $this->developer->id]);
        Notification::assertNotSentTo($this->developer, TaskCreatedAdminNotification::class);
    }

    public function test_finance_notifications_reach_active_enabled_developer_with_existing_finance_roles(): void
    {
        $client = Client::factory()->create();
        $invoice = Invoice::create([
            'client_id' => $client->id, 'created_by' => $this->admin->id, 'number' => 'TEST-ADMIN',
            'issue_date' => today()->subDays(10), 'due_date' => today()->subDay(), 'status' => 'issued',
            'currency' => 'EUR', 'subtotal' => 100, 'tax_amount' => 22, 'total' => 122, 'paid_total' => 0,
        ]);
        $this->artisan('notify:overdue-invoices')->assertSuccessful();
        Notification::assertSentTo([$this->admin, $this->developer, $this->administration], InvoiceOverdueNotification::class);
        Notification::assertNotSentTo([$this->normal, $this->inactive], InvoiceOverdueNotification::class);

        $payment = Payment::create([
            'invoice_id' => $invoice->id, 'client_id' => $client->id, 'created_by' => $this->admin->id,
            'amount' => 50, 'payment_date' => today(), 'method' => 'bank_transfer',
        ]);
        app(SendPaymentRecordedNotification::class)->handle(new PaymentRecorded($payment));
        Notification::assertSentTo([$this->admin, $this->developer, $this->administration], PaymentRecordedNotification::class);
        Notification::assertNotSentTo([$this->normal, $this->inactive], PaymentRecordedNotification::class);
    }

    public function test_shooting_notifies_enabled_developer_with_an_admin_route(): void
    {
        $marketing = User::factory()->create(['role' => UserRole::Marketing]);
        $photographer = User::factory()->create(['role' => UserRole::Photographer]);
        $project = Project::factory()->create();
        $project->users()->attach($marketing->id, ['role' => 'owner']);
        $this->actingAs($marketing);
        $shoot = app(CreateShootRequestAction::class)->execute([
            'project_id' => $project->id, 'photographer_id' => $photographer->id,
            'title' => 'Shooting di prova', 'location' => 'Sede di prova',
            'slots' => [['date' => now()->addWeek()->toDateString(), 'period' => 'morning']],
        ], $marketing->id);
        $this->actingAs($photographer);
        app(PhotographerRespondAction::class)->execute($shoot, null, 'Nuova data richiesta');
        Notification::assertSentTo($this->developer, ShootingWorkflowNotification::class,
            fn (ShootingWorkflowNotification $notification) => str_contains($notification->toArray($this->developer)['url'], '/admin/shooting/'));
        Notification::assertNotSentTo($this->normal, ShootingWorkflowNotification::class);
    }
}
