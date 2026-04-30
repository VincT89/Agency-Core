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

        $platforms = $project->getServiceOption('platforms', []);
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

        $shoot = $project->shoots()->first();

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
                'brief' => $project->brief,
                'description' => $project->description,
                'n8n_request_id' => $project->n8n_request_id,
                'media' => $project->media->map(function ($media) {
                    return [
                        'id' => $media->id,
                        'source' => $media->source,
                        'url' => url(\Illuminate\Support\Facades\Storage::disk($media->disk)->url($media->path)),
                        'filename' => $media->original_name,
                        'mime_type' => $media->mime_type,
                        'size' => $media->size,
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

            \App\Jobs\SendN8nRequestJob::dispatch($payload, $project->id, 'one_shot');
        }
    }
}
