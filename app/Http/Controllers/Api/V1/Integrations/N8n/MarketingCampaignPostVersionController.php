<?php

namespace App\Http\Controllers\Api\V1\Integrations\N8n;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Domain\Social\Actions\AddMarketingCampaignPostVersionFromN8nAction;
use App\Http\Requests\Api\V1\Integrations\N8n\StoreMarketingCampaignPostVersionRequest;
use App\Models\MarketingCampaignPost;
use Illuminate\Http\JsonResponse;

class MarketingCampaignPostVersionController extends Controller
{
    public function store(StoreMarketingCampaignPostVersionRequest $request, MarketingCampaignPost $post, AddMarketingCampaignPostVersionFromN8nAction $action): JsonResponse
    {
        $validated = $request->validated();

        \Illuminate\Support\Facades\Log::info('Ricevuto callback N8N', [
            'post_id' => $post->id,
            'ip' => $request->ip(),
            'request_id' => $validated['request_id'] ?? null
        ]);

        $data = \App\Domain\Social\DTOs\AddMarketingCampaignPostVersionData::fromArray(
            $post->id, 
            $validated
        );

        $result = $action->execute($data);

        return match($result->outcome) {
            'created' => response()->json([
                'status' => 'created',
                'data' => [
                    'post_id' => $post->id,
                    'version_id' => $result->version->id,
                    'version_number' => $result->version->version_number,
                ]
            ], 201),
            
            'duplicate' => response()->json([
                'status' => 'duplicate',
                'version_id' => $result->version->id ?? null,
                'reason' => $result->reason ?? 'request_already_processed',
            ], 200),
            
            'ignored' => response()->json([
                'status' => 'ignored',
                'reason' => $result->reason ?? 'post_already_finalized',
            ], 200),
            
            'conflict' => response()->json([
                'status' => 'conflict',
                'reason' => $result->reason ?? 'request_id_mismatch_or_used',
            ], 409),
            
            default => response()->json([
                'status' => 'error',
                'reason' => 'unknown_outcome',
            ], 500),
        };
    }
}
