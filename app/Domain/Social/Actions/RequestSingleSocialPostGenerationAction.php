<?php

namespace App\Domain\Social\Actions;

use App\Models\MarketingProject;
use App\Services\Integrations\N8n\N8nClient;

class RequestSingleSocialPostGenerationAction
{
    public function __construct(private N8nClient $n8nClient) {}

    public function execute(MarketingProject $project): void
    {
        $payload = [
            'type' => 'one_shot',
            'marketing_project_id' => $project->id,
            'client_id' => $project->client_id,
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

        $this->n8nClient->requestSingleSocialPostGeneration($payload);
    }
}
