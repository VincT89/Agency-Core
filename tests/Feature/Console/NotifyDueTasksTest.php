<?php

namespace Tests\Feature\Console;

use App\Models\Task;
use App\Models\User;
use App\Models\Project;
use App\Models\Client;
use App\Notifications\TaskDueSoonNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotifyDueTasksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_it_notifies_active_users_for_due_tasks_without_auth(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $client = Client::factory()->create();
        $project = Project::factory()->create(['client_id' => $client->id]);
        
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'assigned_to' => $user->id,
            'due_date' => today()->addDay(),
            'status' => 'todo',
        ]);

        // Assicuriamoci che non ci sia utente autenticato
        $this->assertGuest();

        $this->artisan('notify:due-tasks')
            ->expectsOutputToContain('Inviate 1 notifiche')
            ->assertExitCode(0);

        Notification::assertSentTo($user, TaskDueSoonNotification::class, function ($notification) use ($task) {
            return $notification->task->id === $task->id;
        });
    }

    public function test_it_does_not_notify_for_tasks_outside_due_interval(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        
        Task::factory()->create([
            'assigned_to' => $user->id,
            'due_date' => today()->addDays(2), // outside
            'status' => 'todo',
        ]);

        $this->artisan('notify:due-tasks')
            ->expectsOutputToContain('Inviate 0 notifiche')
            ->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_it_does_not_notify_inactive_users(): void
    {
        $user = User::factory()->create(['status' => 'inactive']);
        
        Task::factory()->create([
            'assigned_to' => $user->id,
            'due_date' => today()->addDay(),
            'status' => 'todo',
        ]);

        $this->artisan('notify:due-tasks')
            ->expectsOutputToContain('Inviate 0 notifiche')
            ->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_it_prevents_duplicate_notifications(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        
        $task = Task::factory()->create([
            'assigned_to' => $user->id,
            'due_date' => today()->addDay(),
            'status' => 'todo',
        ]);

        // Prima esecuzione
        $this->artisan('notify:due-tasks')->assertExitCode(0);
        Notification::assertSentTo($user, TaskDueSoonNotification::class);
        
        // Simula la notifica nel database (come avviene realmente col Notifiable trait)
        \Illuminate\Support\Facades\DB::table('notifications')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'type' => TaskDueSoonNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(['type' => 'task_due_soon', 'task_id' => $task->id]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Notification::fake(); // Reset notifiche

        // Seconda esecuzione
        $this->artisan('notify:due-tasks')
            ->expectsOutputToContain('Inviate 0 notifiche')
            ->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_it_excludes_completed_tasks(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        
        Task::factory()->create([
            'assigned_to' => $user->id,
            'due_date' => today()->addDay(),
            'status' => 'done',
        ]);

        $this->artisan('notify:due-tasks')
            ->expectsOutputToContain('Inviate 0 notifiche')
            ->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_it_excludes_unassigned_tasks(): void
    {
        Task::factory()->create([
            'assigned_to' => null,
            'due_date' => today()->addDay(),
            'status' => 'todo',
        ]);

        $this->artisan('notify:due-tasks')
            ->expectsOutputToContain('Inviate 0 notifiche')
            ->assertExitCode(0);

        Notification::assertNothingSent();
    }
}
