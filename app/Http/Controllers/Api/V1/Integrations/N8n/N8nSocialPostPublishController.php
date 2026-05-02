<?php

namespace App\Http\Controllers\Api\V1\Integrations\N8n;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SocialPost;
use App\Domain\Social\Actions\PublishSocialPostFromN8nAction;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Validator;
use Exception;

class N8nSocialPostPublishController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PublishSocialPostFromN8nAction $action
    ) {}

    public function store(Request $request, SocialPost $post)
    {
        $validator = Validator::make($request->all(), [
            'n8n_execution_id' => ['required', 'string'],
            'platform' => ['required', 'in:instagram,facebook,linkedin,tiktok'],
            'external_post_id' => ['required', 'string'],
            'external_post_url' => ['nullable', 'url'],
            'published_at' => ['required', 'date'],
        ]);

        if ($validator->fails()) {
            return $this->error('Validazione fallita', $validator->errors()->toArray(), 422);
        }

        try {
            $updatedPost = $this->action->execute($post, $validator->validated());

            return $this->success([
                'social_post_id' => $updatedPost->id,
                'status' => $updatedPost->status->value,
                'publication_status' => $updatedPost->publication_status,
                'external_post_id' => $updatedPost->external_post_id,
            ]);
            
        } catch (Exception $e) {
            return $this->error('Errore durante la pubblicazione del post: ' . $e->getMessage(), [], 500);
        }
    }
}
