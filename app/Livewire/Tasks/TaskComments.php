<?php

namespace App\Livewire\Tasks;

use App\Enums\Social\CommentSource;
use App\Models\Task;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Locked;
use Livewire\Component;

class TaskComments extends Component
{
    use AuthorizesRequests;

    #[Locked]
    public Task $task;

    public string $body = '';

    public string $delivery_mode = 'internal';

    protected function rules()
    {
        return [
            'body' => 'required|string|max:5000',
            'delivery_mode' => 'nullable|string|in:internal,send_to_client_via_sody',
        ];
    }

    public function mount(Task $task)
    {
        abort_if(auth()->user()->isCommercial(), 403);
        $this->authorize('view', $task);
        $this->task = $task;
    }

    public function addComment()
    {
        $this->authorize('update', $this->task);

        $validated = $this->validate();

        $commentData = [
            'user_id' => auth()->id(),
            'body' => $validated['body'],
            'source' => CommentSource::Internal,
        ];

        $this->task->comments()->create($commentData);

        $this->body = '';
        $this->delivery_mode = 'internal';

        // Refresh the task to reload comments
        $this->task->load('comments.user');
    }

    public function deleteComment(int $id): void
    {
        $this->authorize('update', $this->task);
        $comment = $this->task->comments()->whereKey($id)->firstOrFail();
        $this->authorize('delete', $comment);

        $comment->delete();
        $this->task->load('comments.user');
    }

    public function render()
    {
        abort_if(auth()->user()->isCommercial(), 403);
        $this->authorize('view', $this->task);

        return view('livewire.tasks.task-comments');
    }
}
