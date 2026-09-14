<?php

namespace App\Domain\Quotes;

use App\Domain\Core\Actions\CreateProjectAction;
use App\Models\Project;
use App\Models\Quote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateProjectFromQuoteAction
{
    public function execute(Quote $quote, array $data): Project
    {
        return DB::transaction(function () use ($quote, $data) {
            $quote = Quote::lockForUpdate()->findOrFail($quote->id);
            Gate::authorize('createProject', $quote);
            if ($quote->project_id) {
                return $quote->project;
            }
            $data['client_id'] = $quote->client_id;
            $project = app(CreateProjectAction::class)->execute($data);
            $quote->update(['project_id' => $project->id]);
            if ($quote->ticket_id) {
                $quote->ticket()->whereNull('project_id')->where('client_id', $quote->client_id)
                    ->update(['project_id' => $project->id]);
            }

            return $project;
        });
    }
}
