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
            'n8n_execution_id' => ['required', 'string', 'max:255'],
            'marketing_project_id' => ['required', 'exists:marketing_projects,id'],
            'editorial_plan_id' => ['nullable', 'exists:editorial_plans,id'],
            'editorial_plan_slot_id' => ['nullable', 'exists:editorial_plan_slots,id'],
            'title' => ['required', 'string', 'max:255'],
            'caption' => ['required', 'string'],
            'image_url' => ['required', 'url'],
            'format' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->error('Validazione fallita', $validator->errors()->toArray(), 422);
        }

        try {
            $result = $this->action->execute($validator->validated());

            if (is_array($result) && isset($result['idempotent'])) {
                return $this->success([
                    'social_post_id' => $result['social_post']->id,
                    'version_id' => $result['version']->id,
                    'status' => $result['social_post']->status->value,
                    'idempotent' => true,
                ], 200);
            }

            return $this->success([
                'social_post_id' => $result->id,
                'version_id' => $result->current_version_id,
                'status' => $result->status->value,
            ], 201);
            
        } catch (Exception $e) {
            return $this->error('Errore durante la creazione del Social Post: ' . $e->getMessage(), [], 500);
        }
    }
}
