<?php

namespace Tests\Feature\Workflow;

use App\Models\{Client, Project, Task, User};
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class NotificationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_e2e_flow(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $developer = User::factory()->create(['role' => UserRole::Developer]);

        $client = Client::create(['name' => 'Notif Corp', 'slug' => 'notif-corp', 'status' => 'active']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Notif Setup', 'slug' => 'notif-setup', 'status' => 'active']);
        $project->users()->attach($developer->id, ['role' => 'dev', 'assignment_status' => 'active', 'assigned_at' => now()]);

        $action = new \App\Domain\Core\Actions\CreateTaskAction();
        // Trigger: Task Assigned Notification
        $this->actingAs($admin); // because Action uses auth()->id()
        $task = $action->execute([
            'project_id' => $project->id,
            'assigned_to' => $developer->id,
            'title' => 'Assigned Task E2E',
            'status' => 'todo',
            'due_date' => today()->addDay(),
        ]);

        // Verifico arrivo
        $this->assertEquals(1, $developer->notifications()->count());
        $notification = $developer->notifications()->first();
        $this->assertEquals('task_assigned', $notification->data['type']);

        // Esegui Scheduler Due Soon
        $this->artisan('notify:due-tasks')->assertSuccessful();

        $this->assertEquals(2, $developer->notifications()->count());
        $dueSoonNotif = $developer->notifications()->where('data->type', 'task_due_soon')->first();

        // Let's mark as read via UI endpoint
        $response = $this->actingAs($developer)->post(route('notifications.read', $dueSoonNotif->id));
        $response->assertRedirect($dueSoonNotif->data['url']);

        $this->assertNotNull($dueSoonNotif->refresh()->read_at);

        // Delete notifica
        $responseDel = $this->actingAs($developer)->delete(route('notifications.destroy', $notification->id));
        $responseDel->assertRedirect();
        
        $this->assertEquals(1, $developer->notifications()->count());
    }
}
