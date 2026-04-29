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

        if (! $projectId || ! $clientId) {
            return;
        }

        $exists = Project::query()
            ->where('id', $projectId)
            ->where('client_id', $clientId)
            ->exists();

        if (! $exists) {
            $validator->errors()->add(
                'project_id',
                'Il progetto selezionato non appartiene al cliente indicato.'
            );
        }
    }
}
