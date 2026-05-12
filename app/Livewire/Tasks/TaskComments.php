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

        $deliveryMode = $validated['delivery_mode'] ?? 'internal';
        $sendToSody = $deliveryMode === 'send_to_client_via_sody';

        $commentData = [
            'user_id' => auth()->id(),
            'body' => $validated['body'],
            'source' => CommentSource::Internal,
        ];

        if ($sendToSody) {
            $commentData['source'] = CommentSource::Operator;
            $commentData['delivery_channel'] = 'sody';
            $commentData['delivery_status'] = 'pending';
            $commentData['delivery_requested_at'] = now();
        }

        $comment = $this->task->comments()->create($commentData);

        if ($sendToSody) {
            $clientId = $this->task->project?->client_id;
            
            $comment->update(['idempotency_key' => "task_{$this->task->id}_comment_{$comment->id}"]);

            $session = null;
            if ($clientId) {
                $session = ChatbotClientSession::where('client_id', $clientId)
                    ->where('session_type', 'task')
                    ->where('session_id', $this->task->id)
                    ->first();
            }

            if (!$session) {
                $comment->update([
                    'delivery_status' => 'failed',
                    'delivery_error' => 'Chatbot session not found'
                ]);
            } else {
                try {
                    $payload = [
                        'message_id' => "task_comment_{$comment->id}",
                        'client_id' => $clientId,
                        'session_type' => 'task',
                        'session_id' => $this->task->id,
                        'message' => $comment->body,
                        'source' => 'operator',
                        'idempotency_key' => $comment->idempotency_key,
                        'callback_url' => route('api.v1.integrations.n8n.chatbot.outgoing-messages.status', ['messageId' => "task_comment_{$comment->id}"]),
                    ];

                    $client = $this->task->project?->client;
                    if ($client && $client->phone) {
                         $payload['phone'] = $client->phone;
                    }

                    app(N8nClient::class)->sendChatbotOutgoingMessage($payload);

                    $comment->update(['delivery_status' => 'processing']);
                } catch (\Exception $e) {
                    $comment->update([
                        'delivery_status' => 'failed',
                        'delivery_error' => $e->getMessage()
                    ]);
                }
            }
        }

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
