<?php

namespace App\Http\Requests\Concerns;

use App\Models\Project;
use App\Models\Scopes\ProjectSupremacyScope;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesTicketInput
{
    public function rules(): array
    {
        $commercial = $this->user()->isCommercial();

        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'project_id' => [Rule::requiredIf($this->input('type') !== 'quote'), 'nullable', 'integer', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::in(Ticket::TYPES)],
            'priority' => ['required', Rule::in(Ticket::PRIORITIES)],
            'requested_department' => ['nullable', Rule::in(array_keys(Ticket::DEPARTMENTS))],
            'assigned_to' => $commercial ? ['prohibited'] : ['nullable', 'integer', 'exists:users,id'],
            'assignment_department' => $commercial ? ['prohibited'] : ['nullable', Rule::enum(\App\Enums\UserRole::class)],
            'status' => $commercial ? ['prohibited'] : ['required', Rule::in(Ticket::STATUSES)],
            'opened_at' => $commercial ? ['prohibited'] : ['nullable', 'date'],
            'due_date' => $commercial ? ['prohibited'] : ['nullable', 'date'],
            'resolution_notes' => $commercial ? ['prohibited'] : ['nullable', 'string'],
            'notes' => $commercial ? ['prohibited'] : ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->filled('project_id')) {
                $projects = Project::query();
                if ($this->user()->isCommercial()) {
                    $projects->withoutGlobalScope(ProjectSupremacyScope::class);
                }
                $project = $projects->find($this->input('project_id'));
                if (!$project || (int) $project->client_id !== (int) $this->input('client_id')) {
                    $validator->errors()->add('project_id', 'Seleziona un progetto accessibile appartenente al cliente indicato.');
                }
            }

            if (!$this->filled('assigned_to') || $this->user()->isCommercial()) {
                return;
            }

            $assignee = User::find($this->input('assigned_to'));
            $ticket = new Ticket(['project_id' => $this->input('project_id'), 'assigned_to' => $assignee?->id]);
            $current = $this->route('ticket');
            $unchanged = $current instanceof Ticket
                && (int) $current->assigned_to === (int) $assignee?->id
                && (int) $current->project_id === (int) $this->input('project_id');

            if (!$unchanged && (!$assignee || $assignee->status !== 'active' || !Gate::forUser($assignee)->allows('view', $ticket))) {
                $validator->errors()->add('assigned_to', 'Seleziona un referente attivo abilitato a questo ticket e al suo progetto. Per gli altri reparti usa un Task collegato.');
            }
            if ($this->filled('assignment_department') && $assignee?->role->value !== $this->input('assignment_department')) {
                $validator->errors()->add('assigned_to', 'Il referente non appartiene al reparto selezionato.');
            }
        }];
    }
}
