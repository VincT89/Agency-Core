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
        if ($project->status->value !== MarketingProjectStatus::Draft->value) {
            throw new \Exception('Il progetto è già stato inviato a n8n o non è in stato Bozza.');
        }
        $project->update([
            'status' => MarketingProjectStatus::SubmittedToN8n->value,
            'n8n_request_id' => Str::uuid()->toString(),
            'submitted_to_n8n_at' => now(),
        ]);

        if ($project->type->value === 'one_shot') {
            $this->requestSingleAction->execute($project);
        }
    }
}
