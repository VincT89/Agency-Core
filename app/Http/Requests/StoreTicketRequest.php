<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use App\Http\Requests\Concerns\ValidatesProjectOwnership;

class StoreTicketRequest extends FormRequest
{
    use ValidatesProjectOwnership;
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'exists:clients,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::in(Ticket::TYPES)],
            'status' => ['required', Rule::in(Ticket::STATUSES)],
            'priority' => ['required', Rule::in(Ticket::PRIORITIES)],
            'opened_at' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'resolution_notes' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->withProjectOwnershipCheck($validator),
        ];
    }
}