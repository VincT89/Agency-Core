<?php

namespace App\Http\Controllers\Api\V1\Integrations\N8n;

use App\Http\Controllers\Controller;
use App\Models\ChatbotClientSession;
use App\Models\MarketingCampaignPost;
use App\Models\Ticket;
use App\Models\MarketingCampaignPostComment;
use App\Models\TicketComment;
use App\Enums\Social\CommentSource;
use App\Enums\Social\MarketingCampaignPostStatus;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Client;
use App\Services\Chatbot\PhoneNormalizer;
use App\Enums\UserRole;
use App\Notifications\ChatbotClientInteractionNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class N8nChatbotController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'client_id' => ['nullable', 'integer', 'exists:clients,id', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'required_without:client_id'],
            'session_type' => ['required', 'string', Rule::in(['marketing', 'ticket'])],
            'session_id' => ['required', 'integer'],
            'message' => ['required', 'string'],
            'type' => ['required', 'string', Rule::in(['comment', 'approval', 'change_request'])],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        $client = null;

        if (!empty($validated['client_id'])) {
            $client = Client::findOrFail($validated['client_id']);
        }

        if (!empty($validated['phone'])) {
            $normalizedPhone = app(PhoneNormalizer::class)->normalize($validated['phone']);

            $clientByPhone = Client::where('normalized_phone', $normalizedPhone)->first();

            if (!$clientByPhone) {
                return response()->json([
                    'error' => 'Cliente non trovato per il telefono indicato.',
                ], 404);
            }

            if ($client && $client->id !== $clientByPhone->id) {
                return response()->json([
                    'error' => 'Payload incoerente: client_id e phone appartengono a clienti diversi.',
                ], 409);
            }

            $client = $clientByPhone;
        }

        // Step 2.5, 2.6 & 2.7 - Creazione commento, Stati post e Notifiche
        if ($validated['session_type'] === 'marketing') {
            $chatbotPost = \App\Models\Chatbot\ChatbotMarketingPost::query()
                ->where('marketing_campaign_post_id', $validated['session_id'])
                ->where('client_id', $client->id)
                ->first();

            if (! $chatbotPost) {
                return response()->json([
                    'error' => 'Post non trovato per questo cliente nel contesto chatbot.',
                ], 404);
            }

            $post = MarketingCampaignPost::with('campaign')->find($chatbotPost->marketing_campaign_post_id);
            if (!$post) {
                return response()->json(['error' => 'Post originale non trovato (possibile disallineamento cache).'], 404);
            }

            ChatbotClientSession::updateOrCreate([
                'client_id' => $client->id,
                'session_type' => $validated['session_type'],
                'session_id' => $validated['session_id'],
            ]);

            MarketingCampaignPostComment::create([
                'marketing_campaign_post_id' => $post->id,
                'marketing_campaign_post_version_id' => $post->current_version_id, // opzionale, per associare alla versione
                'user_id' => null,
                'body' => $validated['message'],
                'source' => CommentSource::Client,
                'type' => $validated['type'],
                'visibility' => \App\Enums\Social\MarketingCampaignPostCommentVisibility::Client,
            ]);

            if ($validated['type'] === 'approval') {
                $post->update(['status' => MarketingCampaignPostStatus::ClientApproved]);
            } elseif ($validated['type'] === 'change_request') {
                $post->update(['status' => MarketingCampaignPostStatus::ClientChangesRequested]);
            }

            // Notifiche
            $notifiables = User::whereIn('role', [UserRole::Admin, UserRole::Marketing])->get()->unique('id')->values();
            Notification::send($notifiables, new ChatbotClientInteractionNotification($post, $validated['type']));

        } elseif ($validated['session_type'] === 'ticket') {
            $chatbotTicket = \App\Models\Chatbot\ChatbotTicket::query()
                ->where('ticket_id', $validated['session_id'])
                ->where('client_id', $client->id)
                ->first();

            if (! $chatbotTicket) {
                return response()->json([
                    'error' => 'Ticket non trovato per questo cliente nel contesto chatbot.',
                ], 404);
            }

            $ticket = Ticket::withoutGlobalScope(\App\Models\Scopes\ProjectSupremacyScope::class)
                ->find($chatbotTicket->ticket_id);

            if (!$ticket) {
                return response()->json(['error' => 'Ticket originale non trovato (possibile disallineamento cache).'], 404);
            }

            ChatbotClientSession::updateOrCreate([
                'client_id' => $client->id,
                'session_type' => $validated['session_type'],
                'session_id' => $validated['session_id'],
            ]);

            TicketComment::create([
                'ticket_id' => $ticket->id,
                'user_id' => null,
                'body' => $validated['message'],
                'source' => CommentSource::Client,
            ]);

            // Notifiche
            $notifiables = User::where('role', UserRole::Admin)->get();
            if ($ticket->assignee) {
                $notifiables->push($ticket->assignee);
            }
            $notifiables = $notifiables->unique('id')->values();
            Notification::send($notifiables, new ChatbotClientInteractionNotification($ticket, $validated['type']));
        }

        return response()->json([
            'success' => true,
            'message' => 'Messaggio del cliente salvato con successo.'
        ]);
    }

    public function updateOutgoingMessageStatus(Request $request, string $messageId)
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['sent', 'failed'])],
            'external_message_id' => ['nullable', 'string'],
            'error' => ['nullable', 'string'],
        ]);

        $comment = null;

        if (str_starts_with($messageId, 'ticket_comment_')) {
            $id = str_replace('ticket_comment_', '', $messageId);
            $comment = \App\Models\TicketComment::find($id);
        } elseif (str_starts_with($messageId, 'task_comment_')) {
            $id = str_replace('task_comment_', '', $messageId);
            $comment = \App\Models\TaskComment::find($id);
        } else {
            return response()->json(['error' => 'Formato messageId non supportato.'], 400);
        }

        if (!$comment) {
            return response()->json(['error' => 'Messaggio non trovato.'], 404);
        }

        if ($comment->delivery_channel !== 'sody') {
            return response()->json(['error' => 'Il messaggio non è configurato per la delivery via Sody.'], 400);
        }

        if ($comment->delivery_status === $validated['status']) {
            return response()->json([
                'success' => true,
                'message' => 'Stato di invio già aggiornato (idempotenza).',
                'idempotent' => true
            ]);
        }

        if (!in_array($comment->delivery_status, ['pending', 'processing'])) {
            return response()->json(['error' => 'Il messaggio non è in uno stato aggiornabile.'], 400);
        }

        $comment->update([
            'delivery_status' => $validated['status'],
            'delivered_at' => $validated['status'] === 'sent' ? now() : null,
            'external_message_id' => $validated['external_message_id'] ?? $comment->external_message_id,
            'delivery_error' => $validated['error'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Stato di invio aggiornato con successo.'
        ]);
    }
}
