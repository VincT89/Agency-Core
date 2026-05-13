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
