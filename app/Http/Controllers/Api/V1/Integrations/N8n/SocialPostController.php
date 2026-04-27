<?php

namespace App\Http\Controllers\Api\V1\Integrations\N8n;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Domain\Social\Actions\ReceiveSocialPostFromN8nAction;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Validator;
use Exception;

class SocialPostController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ReceiveSocialPostFromN8nAction $action
    ) {}

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'external_id' => ['nullable', 'string'],
            'project_id' => ['required', 'exists:projects,id'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'title' => ['required', 'string', 'max:255'],
            'caption' => ['required', 'string'],
            'image_url' => ['required', 'url'],
            'format' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->error('Validazione fallita', $validator->errors()->toArray(), 422);
        }

        try {
            $post = $this->action->execute($validator->validated());

            return $this->success([
                'social_post_id' => $post->id,
                'version_id' => $post->current_version_id,
                'status' => $post->status->value,
            ], 201);
            
        } catch (Exception $e) {
            return $this->error('Errore durante la creazione del Social Post: ' . $e->getMessage(), [], 500);
        }
    }
}
