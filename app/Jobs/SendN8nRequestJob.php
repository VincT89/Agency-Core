<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\MarketingProject;
use App\Models\EditorialPlan;
use App\Services\Integrations\N8n\N8nClient;
use Illuminate\Support\Facades\Log;
use App\Enums\Social\MarketingProjectStatus;
use App\Enums\Social\EditorialPlanStatus;
use App\Enums\Social\EditorialPlanSlotStatus;

class SendN8nRequestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30, 60];

    public function __construct(
        public array $payload,
        public int $projectId,
        public string $type = 'one_shot'
    ) {}

    public function handle(N8nClient $client): void
    {
        if ($this->type === 'one_shot') {
            $client->requestSingleSocialPostGeneration($this->payload);
        } else {
            $client->requestEditorialPlanGeneration($this->payload);
        }

        MarketingProject::where('id', $this->projectId)->update([
            'status' => MarketingProjectStatus::SubmittedToN8n->value
        ]);

        if ($this->type === 'editorial_plan') {
            $plan = EditorialPlan::where('marketing_project_id', $this->projectId)->first();
            if ($plan) {
                $plan->update(['status' => EditorialPlanStatus::SubmittedToN8n->value]);
                $plan->slots()->where('status', EditorialPlanSlotStatus::QueuedToN8n->value)->update([
                    'status' => EditorialPlanSlotStatus::SubmittedToN8n->value,
                ]);
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('N8n job definitely failed after retries', [
            'project_id' => $this->projectId,
            'error' => $e->getMessage(),
        ]);
        
        MarketingProject::where('id', $this->projectId)->update([
            'status' => MarketingProjectStatus::N8nFailed->value
        ]);

        if ($this->type === 'editorial_plan') {
            $plan = EditorialPlan::where('marketing_project_id', $this->projectId)->first();
            if ($plan) {
                $plan->update(['status' => EditorialPlanStatus::N8nFailed->value]);
                $plan->slots()->where('status', EditorialPlanSlotStatus::QueuedToN8n->value)->update([
                    'status' => EditorialPlanSlotStatus::N8nFailed->value,
                ]);
            }
        }
    }
}
