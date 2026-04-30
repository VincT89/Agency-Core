<?php

namespace App\Domain\Social\Actions;

use App\Models\EditorialPlan;
use App\Enums\Social\EditorialPlanStatus;
use App\Enums\Social\EditorialPlanSlotStatus;

class SubmitEditorialPlanToN8nAction
{
    public function __construct(private RequestEditorialPlanGenerationAction $requestAction) {}

    public function execute(EditorialPlan $plan): void
    {
        if (!in_array($plan->status->value, [EditorialPlanStatus::Draft->value, EditorialPlanStatus::N8nFailed->value])) {
            throw new \Exception('Il piano è già stato inviato a n8n o non è in stato valido per l\'invio.');
        }

        $platforms = $plan->project->getServiceOption('platforms', []);
        $requiresMeta = in_array('facebook', $platforms) || in_array('instagram', $platforms);
        if ($requiresMeta && !$plan->project->client->isMetaReady()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'social_access' => "Il cliente non ha gli accessi Meta Business configurati o verificati. L'invio a n8n è bloccato.",
            ]);
        }
        
        $newRequestId = \Illuminate\Support\Str::uuid()->toString();
        $plan->project->update([
            'n8n_request_id' => $newRequestId,
        ]);

        $plan->update([
            'status' => EditorialPlanStatus::QueuedToN8n->value,
        ]);

        $plan->slots()->where('status', EditorialPlanSlotStatus::Empty->value)->update([
            'status' => EditorialPlanSlotStatus::QueuedToN8n->value,
        ]);

        $shoot = $plan->project->shoots()->first();

        $payload = [
            'type' => 'editorial_plan',
            'marketing_project_id' => $plan->marketing_project_id, // deprecated
            'editorial_plan_id' => $plan->id,
            'client_id' => $plan->project->client_id,
            'project_id' => $plan->project->project_id,
            'project' => $plan->project->project ? [
                'id' => $plan->project->project->id,
                'name' => $plan->project->project->name,
            ] : null,
            'marketing_campaign' => [
                'id' => $plan->project->id,
                'name' => $plan->project->title,
                'legacy_type' => $plan->project->type->value,
                'service_type' => $plan->project->service_type,
                'campaign_structure' => $plan->project->campaign_structure,
                'service_options' => $plan->project->service_options ?? (object)[],
            ],
            'shooting' => [
                'required' => $shoot !== null,
                'linked' => $shoot !== null,
                'status' => $shoot?->status->value ?? 'pending',
            ],
            'brief' => $plan->project->brief,
            'n8n_request_id' => $newRequestId,
            'media' => $plan->project->media->map(function ($media) {
                return [
                    'id' => $media->id,
                    'source' => $media->source,
                    'url' => url(\Illuminate\Support\Facades\Storage::disk($media->disk)->url($media->path)),
                    'filename' => $media->original_name,
                    'mime_type' => $media->mime_type,
                    'size' => $media->size,
                ];
            })->toArray(),
            'plan_details' => [
                'duration_days' => $plan->duration_days,
                'start_date' => $plan->start_date?->format('Y-m-d'),
                'end_date' => $plan->end_date?->format('Y-m-d'),
                'post_count' => $plan->post_count,
            ],
            'slots' => $plan->slots->map(function ($slot) {
                return [
                    'id' => $slot->id,
                    'scheduled_date' => $slot->scheduled_date?->format('Y-m-d'),
                    'scheduled_time' => $slot->scheduled_time,
                    'topic' => $slot->topic,
                    'platforms' => $slot->platforms,
                ];
            })->toArray(),
            'social_access' => $plan->project->client->socialAccounts->map(function ($account) {
                return array_filter([
                    'platform' => $account->platform->value,
                    'access_status' => $account->access_status->value,
                    'access_method' => $account->access_method->value,
                    'business_manager_id' => $account->isMetaPlatform() ? $account->business_manager_id : null,
                ]);
            })->values()->toArray(),
        ];

        \App\Jobs\SendN8nRequestJob::dispatch($payload, $plan->marketing_project_id, 'editorial_plan');
    }
}
