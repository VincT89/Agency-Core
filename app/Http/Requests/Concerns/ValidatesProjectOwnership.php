<?php

namespace App\Http\Requests\Concerns;

use App\Models\Project;
use Illuminate\Validation\Validator;

trait ValidatesProjectOwnership
{
    // Verifica che il progetto appartenga al cliente specificato nella request
    protected function withProjectOwnershipCheck(Validator $validator): void
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

        if ($clientId && $project->client_id != $clientId) {
            $validator->errors()->add(
                'project_id',
                'Il progetto selezionato non appartiene al cliente indicato.'
            );
        }
    }
}
