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
        \Illuminate\Support\Facades\Log::info('Ricevuto callback N8N', [
            'post_id' => $post->id,
            'ip' => $request->ip(),
            'payload' => $request->all()
        ]);

        if ($post->n8n_request_id !== $request->validated('request_id')) {
            \Illuminate\Support\Facades\Log::warning('N8n Callback Security Mismatch', [
                'post_id' => $post->id,
                'expected_request_id' => $post->n8n_request_id,
                'received_request_id' => $request->validated('request_id'),
                'ip' => $request->ip(),
            ]);
            abort(409, 'Conflict: request_id mismatch.');
        }

        $result = $action->execute($post, $request->validated());

        if ($result instanceof MarketingCampaignPost) {
            return response()->json([
                'status' => 'ignored',
                'message' => 'Operazione annullata dall\'utente',
                'post_id' => $post->id,
            ], 200);
        }

        $version = $result;

        return response()->json([
            'status' => 'success',
            'data' => [
                'post_id' => $post->id,
                'version_id' => $version->id,
                'version_number' => $version->version_number,
            ]
        ], 201);
    }
}
