<?php

namespace Tests\Feature;

use App\Domain\Core\Actions\AssignTaskAction;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class TaskReassignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $recipient;
    private User $outsider;
    private Project $project;
    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        $this->admin = $this->user(UserRole::Admin);
        $this->recipient = $this->user(UserRole::Developer);
        $this->outsider = $this->user(UserRole::Developer);
        $this->actingAs($this->admin);
        $this->project = Project::factory()->create(['client_id' => Client::factory()->create()->id, 'status' => 'active']);
        $this->project->users()->attach($this->recipient->id, ['role' => 'developer']);
        $this->task = Task::create([
            'project_id' => $this->project->id, 'created_by' => $this->admin->id,
            'assigned_to' => $this->admin->id, 'title' => 'Task dimostrativa da riassegnare',
            'status' => 'todo', 'priority' => 'medium',
        ]);
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active', 'password_changed_at' => now()]);
    }

    private function payload(array $attributes = []): array
    {
        return array_merge([
            'project_id' => $this->project->id, 'assigned_to' => $this->recipient->id,
            'title' => $this->task->title, 'status' => 'todo', 'priority' => 'medium',
        ], $attributes);
    }

    private function assignmentNotifications(User $user)
    {
        return $user->notifications()->where('type', TaskAssignedNotification::class);
    }

    public function test_admin_reassigns_their_task_to_a_member_who_gets_the_task_and_notification(): void
    {
        $this->patch(route('tasks.update', $this->task), $this->payload())
            ->assertSessionHasNoErrors()->assertRedirect(route('tasks.show', $this->task));
        $this->assertSame($this->recipient->id, $this->task->fresh()->assigned_to);

        $this->actingAs($this->recipient)->get(route('dashboard'))->assertOk()
            ->assertViewHas('otherTasks', fn ($tasks) => $tasks->contains('id', $this->task->id));
        $this->get(route('tasks.index'))->assertOk()->assertSee($this->task->title);
        $this->get(route('tasks.show', $this->task))->assertOk();
        $this->assertSame(1, $this->assignmentNotifications($this->recipient)->count());
        $notice = $this->assignmentNotifications($this->recipient)->sole();
        $this->assertSame($this->task->id, $notice->data['task_id']);
        $this->assertSame(route('tasks.show', $this->task), $notice->data['url']);
        $this->assertSame(0, $this->assignmentNotifications($this->admin)->count());
        $this->assertSame(0, $this->assignmentNotifications($this->outsider)->count());
    }

    public function test_task_cannot_be_reassigned_to_someone_without_project_access(): void
    {
        $this->patchJson(route('tasks.update', $this->task), $this->payload(['assigned_to' => $this->outsider->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('assigned_to');
        $this->assertSame($this->admin->id, $this->task->fresh()->assigned_to);
        $this->assertDatabaseMissing('project_user', ['project_id' => $this->project->id, 'user_id' => $this->outsider->id]);
        $this->assertSame(0, $this->assignmentNotifications($this->outsider)->count());
    }

    public function test_adding_a_legacy_assignee_to_the_project_restores_task_visibility(): void
    {
        $this->task->update(['assigned_to' => $this->outsider->id]);

        $this->actingAs($this->outsider)->get(route('dashboard'))->assertOk()
            ->assertViewHas('otherTasks', fn ($tasks) => !$tasks->contains('id', $this->task->id));
        $this->get(route('tasks.show', $this->task))->assertNotFound();

        $this->actingAs($this->admin);
        $this->project->users()->attach($this->outsider->id, ['role' => 'developer']);

        $this->actingAs($this->outsider)->get(route('dashboard'))->assertOk()
            ->assertViewHas('otherTasks', fn ($tasks) => $tasks->contains('id', $this->task->id));
        $this->get(route('tasks.show', $this->task))->assertOk();
        $this->assertSame($this->outsider->id, $this->task->fresh()->assigned_to);
        $this->assertSame(0, $this->assignmentNotifications($this->outsider)->count());
    }

    public function test_creation_also_rejects_an_assignee_who_cannot_receive_the_task(): void
    {
        $this->postJson(route('tasks.store'), $this->payload(['assigned_to' => $this->outsider->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('assigned_to');
        $this->postJson(route('tasks.store'), $this->payload(['assigned_to' => $this->user(UserRole::Administration)->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('assigned_to');
        $this->assertDatabaseCount('tasks', 1);
    }

    public function test_changing_project_checks_the_existing_assignee_when_the_field_is_omitted(): void
    {
        $this->task->update(['assigned_to' => $this->recipient->id]);
        $otherProject = Project::factory()->create(['client_id' => $this->project->client_id]);
        $payload = $this->payload(['project_id' => $otherProject->id]);
        unset($payload['assigned_to']);
        $this->patchJson(route('tasks.update', $this->task), $payload)->assertUnprocessable()->assertJsonValidationErrors('assigned_to');
        $this->assertSame($this->project->id, $this->task->fresh()->project_id);
    }

    public function test_repeated_saves_and_removing_the_assignee_do_not_send_duplicate_notifications(): void
    {
        $this->patch(route('tasks.update', $this->task), $this->payload())->assertSessionHasNoErrors();
        $this->patch(route('tasks.update', $this->task), $this->payload(['title' => 'Titolo aggiornato', 'status' => 'in_progress']))
            ->assertSessionHasNoErrors();
        $this->patch(route('tasks.update', $this->task), $this->payload(['assigned_to' => null]))->assertSessionHasNoErrors();
        $this->assertSame(1, $this->assignmentNotifications($this->recipient)->count());
        $this->assertNull($this->task->fresh()->assigned_to);
    }

    public function test_creator_is_notified_if_another_user_assigns_the_task_back_to_them(): void
    {
        $this->task->update(['created_by' => $this->recipient->id]);
        $this->patch(route('tasks.update', $this->task), $this->payload())->assertSessionHasNoErrors();
        $this->assertSame(1, $this->assignmentNotifications($this->recipient)->count());

        $this->patch(route('tasks.update', $this->task), $this->payload(['assigned_to' => $this->admin->id]))->assertSessionHasNoErrors();
        $this->assertSame(0, $this->assignmentNotifications($this->admin)->count());
    }

    public function test_assignment_action_refreshes_a_cached_assignee_and_ignores_unchanged_assignments(): void
    {
        $this->task->load('assignee');
        app(AssignTaskAction::class)->execute($this->task, $this->recipient->id);
        app(AssignTaskAction::class)->execute($this->task, $this->recipient->id);
        $this->assertSame(1, $this->assignmentNotifications($this->recipient)->count());
        $this->assertSame(0, $this->assignmentNotifications($this->admin)->count());
    }
}
