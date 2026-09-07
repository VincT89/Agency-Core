<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\Task;
use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Task::class);
    }

    public function rules(): array
    {
        return [
            'project_id'  => [
                'required',
                function ($attribute, $value, $fail) {
                    if (!Project::whereKey($value)->exists()) {
                        $fail('Il progetto selezionato non è valido o non hai i permessi per accedervi.');
                    }
                },
            ],
            'ticket_id'   => [
                'nullable',
                function ($attribute, $value, $fail) {
                    $ticket = Ticket::whereKey($value)->first();
                    if (!$ticket) {
                        $fail('Il ticket selezionato non esiste.');
                    } elseif (!$this->user()->can('view', $ticket)) {
                        $fail('Non puoi collegare questo ticket.');
                    } elseif ($ticket->project_id != $this->project_id) {
                        $fail('Il ticket deve appartenere allo stesso progetto del task.');
                    }
                },
            ],
            'assignment_department' => ['nullable', Rule::enum(\App\Enums\UserRole::class)],
            'assigned_to' => [
                'nullable',
                Rule::exists('users', 'id')->where('status', 'active')->whereNot('role', \App\Enums\UserRole::Commercial->value),
            ],
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status'      => ['required', 'in:todo,in_progress,waiting,done'],
            'priority'    => ['required', 'in:low,medium,high,urgent'],
            'start_date'  => ['nullable', 'date'],
            'due_date'    => ['nullable', 'date'],
            'notes'       => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [function (\Illuminate\Validation\Validator $validator) {
            if ($validator->errors()->isNotEmpty() || !$this->filled('ticket_id') || !$this->filled('assigned_to')) {
                return;
            }
            $assignee = \App\Models\User::find($this->input('assigned_to'));
            $task = new Task(['project_id' => $this->input('project_id'), 'assigned_to' => $assignee?->id]);
            if (!$assignee || !\Illuminate\Support\Facades\Gate::forUser($assignee)->allows('viewAny', Task::class)
                || !\Illuminate\Support\Facades\Gate::forUser($assignee)->allows('view', $task)) {
                $validator->errors()->add('assigned_to', 'Il referente deve essere abilitato ai Task e al progetto selezionato.');
            }
            if ($this->filled('assignment_department') && $assignee?->role->value !== $this->input('assignment_department')) {
                $validator->errors()->add('assigned_to', 'Il referente non appartiene al reparto selezionato.');
            }
        }];
    }
}
