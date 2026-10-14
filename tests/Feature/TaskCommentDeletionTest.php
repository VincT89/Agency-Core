<?php

namespace Tests\Feature;

use App\Enums\Social\CommentSource;
use App\Enums\UserRole;
use App\Livewire\Tasks\TaskComments;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class TaskCommentDeletionTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        $this->author = User::factory()->create(['role' => UserRole::Developer]);
        $this->actingAs($this->author);
        $this->task = Task::factory()->create(['project_id' => null, 'created_by' => $this->author->id, 'assigned_to' => $this->author->id]);
    }

    public function test_author_can_delete_own_comment_without_deleting_other_comments_or_history(): void
    {
        $own = $this->task->comments()->create(['user_id' => $this->author->id, 'body' => 'Nota dimostrativa da eliminare', 'source' => CommentSource::Internal]);
        $other = $this->task->comments()->create(['user_id' => User::factory()->create()->id, 'body' => 'Nota di un collega', 'source' => CommentSource::Internal]);
        $historyCount = DB::table('audit_logs')->count();
        Livewire::test(TaskComments::class, ['task' => $this->task])
            ->assertSee('deleteComment('.$own->id.')', false)
            ->assertDontSee('deleteComment('.$other->id.')', false)
            ->set('body', 'Bozza ancora da inviare')
            ->call('deleteComment', $own->id)
            ->assertDontSee($own->body)
            ->assertSee($other->body)
            ->assertSet('body', 'Bozza ancora da inviare');
        $this->assertModelMissing($own);
        $this->assertModelExists($other);
        $this->assertSame($historyCount, DB::table('audit_logs')->count());
    }

    public function test_even_administrators_cannot_delete_another_authors_comment(): void
    {
        $comment = $this->task->comments()->create(['user_id' => $this->author->id, 'body' => 'Nota privata dell’autore', 'source' => CommentSource::Internal]);
        foreach ([UserRole::Admin, UserRole::OperationsManager] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            Livewire::test(TaskComments::class, ['task' => $this->task])
                ->assertDontSee('deleteComment('.$comment->id.')', false)
                ->call('deleteComment', $comment->id)->assertForbidden();
            $this->assertModelExists($comment);
        }
    }

    public function test_a_comment_from_another_task_cannot_be_deleted_through_this_task(): void
    {
        $otherTask = Task::factory()->create(['project_id' => null, 'created_by' => $this->author->id, 'assigned_to' => $this->author->id]);
        $comment = $otherTask->comments()->create(['user_id' => $this->author->id, 'body' => 'Nota su altra task', 'source' => CommentSource::Internal]);
        $component = Livewire::test(TaskComments::class, ['task' => $this->task]);
        $this->expectException(ModelNotFoundException::class);
        try {
            $component->call('deleteComment', $comment->id);
        } finally {
            $this->assertModelExists($comment);
        }
    }

    public function test_system_and_client_entries_cannot_be_deleted_as_own_comments(): void
    {
        foreach ([[null, CommentSource::Internal], [$this->author->id, CommentSource::Client]] as [$userId, $source]) {
            $comment = $this->task->comments()->create(['user_id' => $userId, 'body' => 'Voce esterna dimostrativa', 'source' => $source]);
            Livewire::test(TaskComments::class, ['task' => $this->task])
                ->assertDontSee('deleteComment('.$comment->id.')', false)
                ->call('deleteComment', $comment->id)->assertForbidden();
            $this->assertModelExists($comment);
        }
    }

    public function test_authorship_does_not_grant_access_after_task_access_is_lost(): void
    {
        $comment = $this->task->comments()->create(['user_id' => $this->author->id, 'body' => 'Nota dimostrativa', 'source' => CommentSource::Internal]);
        $this->task->update(['assigned_to' => User::factory()->create()->id]);
        $this->assertFalse($this->author->can('delete', $comment));
        Livewire::test(TaskComments::class, ['task' => $this->task])->assertForbidden();
        $this->assertModelExists($comment);
    }

    public function test_new_comment_can_be_deleted_and_empty_state_returns(): void
    {
        $component = Livewire::test(TaskComments::class, ['task' => $this->task])
            ->set('body', 'Nuova nota dimostrativa')->call('addComment')->assertHasNoErrors();
        $comment = $this->task->comments()->sole();
        $component->call('deleteComment', $comment->id)->assertSee('Nessun commento ancora presente.');
        $this->assertModelMissing($comment);
    }
}
