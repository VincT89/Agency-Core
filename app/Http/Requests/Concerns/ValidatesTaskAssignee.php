<?php

namespace App\Http\Requests\Concerns;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

trait ValidatesTaskAssignee
{
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $currentTask = $this->route('task');
            $assigneeId = $this->input('assigned_to', $currentTask instanceof Task ? $currentTask->assigned_to : null);
            if (!$assigneeId) {
                return;
            }

            $assignee = User::find($assigneeId);
            $task = new Task(['project_id' => $this->input('project_id'), 'assigned_to' => $assigneeId]);

            if (!$assignee || $assignee->status !== 'active' || !Gate::forUser($assignee)->allows('viewAny', Task::class)) {
                $validator->errors()->add('assigned_to', 'Seleziona un utente attivo abilitato alle task.');
                return;
            }

            if (!Gate::forUser($assignee)->allows('view', $task)) {
                $validator->errors()->add('assigned_to', 'Il destinatario non ha accesso a questo progetto. Aggiungilo al team di commessa o scegli un altro assegnatario.');
            }

            if ($this->filled('assignment_department') && $assignee->role->value !== $this->input('assignment_department')) {
                $validator->errors()->add('assigned_to', 'Il referente non appartiene al reparto selezionato.');
            }
        }];
    }
}
