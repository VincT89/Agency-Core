<?php

namespace App\Http\Controllers\Api\V1\Integrations\N8n;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SocialPost;
use App\Domain\Social\Actions\AddSocialPostVersionFromN8nAction;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Validator;
use Exception;

class SocialPostVersionController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AddSocialPostVersionFromN8nAction $action
    ) {}

    public function store(Request $request, SocialPost $post)
    {
        $validator = Validator::make($request->all(), [
            'external_generation_id' => ['nullable', 'string'],
            'caption' => ['required', 'string'],
            'image_url' => ['required', 'url'],
            'prompt_used' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->error('Validazione fallita', $validator->errors()->toArray(), 422);
        }

        try {
            $version = $this->action->execute($post, $validator->validated());

            return $this->success([
                'social_post_id' => $post->id,
                'version_id' => $version->id,
                'status' => $post->status->value,
            ], 201);
            
        } catch (Exception $e) {
            return $this->error('Errore durante la creazione della versione: ' . $e->getMessage(), [], 500);
        }
    }
}
