<?php

namespace App\Http\Controllers\Api\V1\Integrations\N8n;

use App\Http\Controllers\Controller;
use App\Models\MarketingCampaignPost;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class MarketingCampaignPostFailedController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function store(Request $request, MarketingCampaignPost $post): JsonResponse
    {
        $request->validate([
            'request_id' => ['required', 'string'],
            'error' => ['required', 'string'],
        ]);

        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $post) {
            $post = MarketingCampaignPost::lockForUpdate()->findOrFail($post->id);

            if ($post->n8n_request_id !== $request->input('request_id')) {
                Log::warning('N8N Failed callback ignored due to request_id mismatch', [
                    'post_id' => $post->id,
                    'expected_request_id' => $post->n8n_request_id,
                    'received_request_id' => $request->input('request_id'),
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid request_id for this post.',
                ], 400);
            }

            $modifiableStatuses = [
                \App\Enums\Social\MarketingCampaignPostStatus::PendingN8n->value,
                \App\Enums\Social\MarketingCampaignPostStatus::SubmittedToN8n->value,
                \App\Enums\Social\MarketingCampaignPostStatus::Regenerating->value,
            ];

            if (!in_array($post->status->value, $modifiableStatuses, true)) {
                return response()->json([
                    'status' => 'ignored',
                    'message' => 'Callback di fallimento ignorata: elaborazione già conclusa.',
                ], 200);
            }

            $newStatus = $post->n8n_previous_status?->value;
            // Se lo stato precedente non è valido (es. ancora in transitorio), usa draft
            if (!$newStatus || in_array($newStatus, $modifiableStatuses, true)) {
                $newStatus = \App\Enums\Social\MarketingCampaignPostStatus::Draft->value;
            }

            $post->update([
                'status' => $newStatus,
                'n8n_error' => $request->input('error'),
                'n8n_completed_at' => now(),
            ]);

            Log::info('N8N Failed callback processed', [
                'post_id' => $post->id,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Error status saved.',
            ]);
        });
    }
}
