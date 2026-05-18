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

        Log::info('N8N Failed callback received', [
            'post_id' => $post->id,
            'request_id' => $request->input('request_id'),
            'error' => $request->input('error'),
        ]);

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

        $post->update([
            'status' => $post->n8n_previous_status?->value ?? \App\Enums\Social\MarketingCampaignPostStatus::Generated->value,
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
    }
}
