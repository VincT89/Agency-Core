<?php

namespace App\Http\Requests\Concerns;

use App\Models\Project;
use Illuminate\Validation\Validator;

trait ValidatesProjectOwnership
{
    /** Only invoice forms may opt into the shared container; ordinary projects still belong to one client. */
    protected function withProjectOwnershipCheck(Validator $validator, bool $allowInvoiceContainer = false): void
    {
        $projectId = $this->input('project_id');
        $clientId  = $this->input('client_id');

        if (! $projectId) {
            return;
        }

        $project = Project::query()
            ->whereKey($projectId)
            ->first();

        if (! $project) {
            $validator->errors()->add(
                'project_id',
                'Il progetto selezionato non è valido o non hai i permessi per accedervi.'
            );
            return;
        }

        if ($allowInvoiceContainer && $project->isInvoiceContainer() && $this->user()->canAccessFinance()) {
            return;
        }

        if ($clientId && $project->client_id != $clientId) {
            $validator->errors()->add(
                'project_id',
                'Il progetto selezionato non appartiene al cliente indicato.'
            );
        }
    }
}
