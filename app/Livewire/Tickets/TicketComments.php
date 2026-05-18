<?php

namespace App\Livewire\Tickets;

use Livewire\Component;
use App\Models\Ticket;
use App\Models\ChatbotClientSession;
use App\Enums\Social\CommentSource;
use App\Services\Integrations\N8n\N8nClient;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TicketComments extends Component
{
    use AuthorizesRequests;

    public Ticket $ticket;
    public string $body = '';
    public string $delivery_mode = 'internal';

    protected function rules()
    {
        return [
            'body' => 'required|string|max:5000',
            'delivery_mode' => 'nullable|string|in:internal,send_to_client_via_sody',
        ];
    }

    public function mount(Ticket $ticket)
    {
        $this->ticket = $ticket;
    }

    public function addComment()
    {
        $this->authorize('update', $this->ticket);

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

        $comment = $this->ticket->comments()->create($commentData);

        if ($sendToSody) {
            $comment->update(['idempotency_key' => "ticket_{$this->ticket->id}_comment_{$comment->id}"]);

            $this->sendCommentToSody($comment);
        }

        $this->body = '';
        $this->delivery_mode = 'internal';
        
        // Refresh the ticket to reload comments
        $this->ticket->load('comments.user');
    }

    public function retrySendToSody(int $commentId)
    {
        $this->authorize('update', $this->ticket);

        $comment = $this->ticket->comments()->findOrFail($commentId);

        if ($comment->delivery_channel !== 'sody' || $comment->delivery_status !== 'failed') {
            return;
        }

        $comment->update([
            'delivery_status' => 'pending',
            'delivery_error' => null,
            'delivery_requested_at' => now(),
        ]);

        $this->sendCommentToSody($comment);
        
        $this->ticket->load('comments.user');
    }

    private function sendCommentToSody($comment): void
    {
        $session = ChatbotClientSession::where('client_id', $this->ticket->client_id)
            ->where('session_type', 'ticket')
            ->where('session_id', $this->ticket->id)
            ->first();

        if (!$session) {
            $comment->update([
                'delivery_status' => 'failed',
                'delivery_error' => 'Chatbot session not found'
            ]);
            return;
        }

        try {
            $payload = [
                'message_id' => "ticket_comment_{$comment->id}",
                'client_id' => $this->ticket->client_id,
                'session_type' => 'ticket',
                'session_id' => $this->ticket->id,
                'message' => $comment->body,
                'source' => 'operator',
                'idempotency_key' => $comment->idempotency_key,
                'callback_url' => route('api.v1.integrations.n8n.chatbot.outgoing-messages.status', ['messageId' => "ticket_comment_{$comment->id}"]),
            ];

            $client = $this->ticket->client;
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

    public function render()
    {
        return view('livewire.tickets.ticket-comments');
    }
}
