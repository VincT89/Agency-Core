<?php

namespace App\Domain\Social\Actions;

use App\Models\MarketingProject;
use App\Enums\Social\MarketingProjectStatus;
use Illuminate\Support\Str;

class SubmitMarketingProjectToN8nAction
{
    public function __construct(private RequestSingleSocialPostGenerationAction $requestSingleAction) {}

    public function execute(MarketingProject $project): void
    {
        if (!in_array($project->status->value, [MarketingProjectStatus::Draft->value, MarketingProjectStatus::N8nFailed->value])) {
            throw new \Exception('Il progetto è già stato inviato a n8n o non è in stato valido per l\'invio.');
        }

        $platforms = $project->platforms ?? [];
        $requiresMeta = collect($platforms)->intersect(['facebook', 'instagram'])->isNotEmpty();
        if ($requiresMeta && !$project->client->isMetaReady()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'social_access' => "Il cliente non ha gli accessi Meta Business configurati o verificati. L'invio a n8n è bloccato.",
            ]);
        }
        
        $project->update([
            'status' => MarketingProjectStatus::QueuedToN8n->value,
            'n8n_request_id' => Str::uuid()->toString(),
            'submitted_to_n8n_at' => now(), // can keep this or queued_at
        ]);

        if ($project->type->value === 'one_shot') {
            $payload = [
                'type' => 'one_shot',
                'marketing_project_id' => $project->id, // deprecated
                'client_id' => $project->client_id,
                'project_id' => $project->project_id,
                'project' => $project->project ? [
                    'id' => $project->project->id,
                    'name' => $project->project->name,
                ] : null,
                'marketing_campaign' => [
                    'id' => $project->id,
                    'name' => $project->title,
                ],
                'brief' => $project->brief,
                'description' => $project->description,
                'platforms' => $project->platforms,
                'n8n_request_id' => $project->n8n_request_id,
                'social_access' => $project->client->socialAccounts->map(function ($account) {
                    return array_filter([
                        'platform' => $account->platform->value,
                        'access_status' => $account->access_status->value,
                        'access_method' => $account->access_method->value,
                        'business_manager_id' => $account->isMetaPlatform() ? $account->business_manager_id : null,
                    ]);
                })->values()->toArray(),
            ];

            \App\Jobs\SendN8nRequestJob::dispatch($payload, $project->id, 'one_shot');
        }
    }
}
