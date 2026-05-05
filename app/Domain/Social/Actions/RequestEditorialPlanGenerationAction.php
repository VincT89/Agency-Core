<?php

namespace App\Domain\Social\Actions;

use App\Models\EditorialPlan;
use App\Services\Integrations\N8n\N8nClient;

class RequestEditorialPlanGenerationAction
{
    public function __construct(private N8nClient $n8nClient) {}

    public function execute(EditorialPlan $plan, array $clientContext = []): void
    {
        $plan->loadMissing(['marketingProject.client.socialAccounts', 'marketingProject.project', 'marketingProject.shoots', 'marketingProject.media', 'slots']);
        $project = $plan->marketingProject;

        // Costruzione dinamica client base context
        $clientPayload = [
            'id' => $project->client->id,
            'name' => $project->client->name,
            'company_name' => $project->client->company_name,
        ];

        if (!empty($clientContext['include_logo']) && !empty($clientContext['logo_url'])) {
            $clientPayload['logo_url'] = $clientContext['logo_url'];
        }

        if (!empty($clientContext['include_header']) && !empty($clientContext['activity_description'])) {
            $clientPayload['activity_description'] = $clientContext['activity_description'];
        }

        $payload = [
            'type' => 'editorial_plan',
            'marketing_project_id' => $project->id,
            'editorial_plan_id' => $plan->id,
            'client_id' => $project->client_id,
            'client' => $clientPayload,
            'brief' => $project->brief,
            'description' => $project->description,
            'duration_days' => $plan->duration_days,
            'n8n_request_id' => $project->n8n_request_id,
            'slots' => $plan->slots->map(function ($slot) {
                return [
                    'slot_id' => $slot->id,
                    'date' => $slot->scheduled_date?->format('Y-m-d'),
                    'time' => $slot->scheduled_time,
                    'platforms' => $slot->platforms,
                    'topic' => $slot->topic,
                ];
            })->toArray(),
            'social_access' => $project->client->socialAccounts->map(function ($account) {
                return array_filter([
                    'platform' => $account->platform->value,
                    'access_status' => $account->access_status->value,
                    'access_method' => $account->access_method->value,
                    'business_manager_id' => $account->isMetaPlatform() ? $account->business_manager_id : null,
                ]);
            })->values()->toArray(),
        ];

        $this->n8nClient->requestEditorialPlanGeneration($payload);
    }
}
