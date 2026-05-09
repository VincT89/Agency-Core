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
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'session_type' => ['required', 'string', Rule::in(['marketing', 'ticket'])],
            'session_id' => ['required', 'integer'],
            'message' => ['required', 'string'],
            'type' => ['required', 'string', Rule::in(['comment', 'approval', 'change_request'])],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        // Step 2.5, 2.6 & 2.7 - Creazione commento, Stati post e Notifiche
        if ($validated['session_type'] === 'marketing') {
            $post = MarketingCampaignPost::with('campaign')->find($validated['session_id']);
            if (!$post) {
                return response()->json(['error' => 'Post non trovato.'], 404);
            }
            if ($post->campaign->client_id !== (int) $validated['client_id']) {
                return response()->json(['error' => 'Non autorizzato. Questo post non appartiene al client indicato.'], 403);
            }

            ChatbotClientSession::updateOrCreate([
                'client_id' => $validated['client_id'],
                'session_type' => $validated['session_type'],
                'session_id' => $validated['session_id'],
            ]);

            MarketingCampaignPostComment::create([
                'marketing_campaign_post_id' => $post->id,
                'marketing_campaign_post_version_id' => $post->current_version_id, // opzionale, per associare alla versione
                'user_id' => null,
                'body' => $validated['message'],
                'source' => CommentSource::Client,
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
            $ticket = Ticket::find($validated['session_id']);
            if (!$ticket) {
                return response()->json(['error' => 'Ticket non trovato.'], 404);
            }
            if ($ticket->client_id !== (int) $validated['client_id']) {
                return response()->json(['error' => 'Non autorizzato. Questo ticket non appartiene al client indicato.'], 403);
            }

            ChatbotClientSession::updateOrCreate([
                'client_id' => $validated['client_id'],
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
            Notification::send($notifiables, new ChatbotClientInteractionNotification($ticket, 'comment'));
        }

        return response()->json([
            'success' => true,
            'message' => 'Messaggio del cliente salvato con successo.'
        ]);
    }
}
