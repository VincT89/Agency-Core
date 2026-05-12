<?php

namespace App\Livewire\Tasks;

use Livewire\Component;
use App\Models\Task;
use App\Models\ChatbotClientSession;
use App\Enums\Social\CommentSource;
use App\Services\Integrations\N8n\N8nClient;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TaskComments extends Component
{
    use AuthorizesRequests;

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

    public function render()
    {
        return view('livewire.tasks.task-comments');
    }
}
