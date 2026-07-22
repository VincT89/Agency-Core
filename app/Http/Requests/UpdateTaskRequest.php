<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('task'));
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
                    } elseif ($ticket->project_id != $this->project_id) {
                        $fail('Il ticket deve appartenere allo stesso progetto del task.');
                    }
                },
            ],
            'assigned_to' => [
                'nullable',
                Rule::exists('users', 'id')->where('status', 'active'),
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
}
