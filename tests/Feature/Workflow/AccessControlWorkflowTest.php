<?php

namespace Tests\Feature\Workflow;

use App\Models\{Client, Project, Task, User};
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class AccessControlWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_access_control_security_failures(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $developerInside = User::factory()->create(['role' => UserRole::Developer]);
        $developerOutside = User::factory()->create(['role' => UserRole::Developer]);

        $client = Client::create(['name' => 'Secret Client', 'slug' => 'secret-client', 'status' => 'active']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Top Secret', 'slug' => 'top-secret', 'status' => 'active']);

        $project->users()->attach($developerInside->id, ['role' => 'dev', 'assignment_status' => 'active', 'assigned_at' => now()]);

        $task = Task::create([
            'project_id' => $project->id,
            'created_by' => $admin->id,
            'title' => 'Secret Task Title',
            'status' => 'todo',
        ]);

        // Hacker (DeveloperOutside) tries to view Secret Task
        // Thanks to Project Supremacy in Policies => MUST FAIL
        $response1 = $this->actingAs($developerOutside)->get(route('tasks.show', $task));
        $response1->assertNotFound();

        // Hacker tries to directly hit an invalid or forbidden project
        $response2 = $this->actingAs($developerOutside)->get(route('projects.show', $project));
        // ProjectPolicy returns false
        $response2->assertNotFound();

        // Cross-notification deletion
        $adminNotification = $admin->notifications()->create([
            'id' => \Illuminate\Support\Str::uuid(),
            'type' => 'App\Notifications\TaskAssignedNotification',
            'data' => [],
            'read_at' => null,
        ]);

        // Hacker attempts to delete admin's notification
        // Should 404 because `NotificationController` uses auth()->user()->notifications()->findOrFail()
        $response3 = $this->actingAs($developerOutside)->delete(route('notifications.destroy', $adminNotification->id));
        $response3->assertNotFound();
    }
}
