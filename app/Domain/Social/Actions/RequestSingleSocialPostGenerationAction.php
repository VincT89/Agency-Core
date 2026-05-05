<?php

namespace App\Domain\Social\Actions;

use App\Models\MarketingProject;
use App\Services\Integrations\N8n\N8nClient;

class RequestSingleSocialPostGenerationAction
{
    public function __construct(private N8nClient $n8nClient) {}

    public function execute(MarketingProject $project, array $clientContext = []): void
    {
        $project->loadMissing(['client.socialAccounts', 'project', 'shoots', 'media']);
        $shoot = $project->shoots()->first();

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
            'type' => 'one_shot',
            'marketing_project_id' => $project->id,
            'client_id' => $project->client_id,
            'client' => $clientPayload,
            'brief' => $project->brief,
            'description' => $project->description,
            'marketing_campaign' => [
                'id' => $project->id,
                'name' => $project->title,
                'legacy_type' => $project->type->value,
                'service_type' => $project->service_type,
                'campaign_structure' => $project->campaign_structure,
                'service_options' => $project->service_options ?? (object)[],
            ],
            'shooting' => [
                'required' => $shoot !== null,
                'linked' => $shoot !== null,
                'status' => $shoot?->status->value ?? 'pending',
            ],
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
