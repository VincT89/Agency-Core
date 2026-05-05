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
        $version = $action->execute($post, $request->validated());

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
