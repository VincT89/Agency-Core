<?php

namespace App\Http\Requests;

use App\Models\CalendarEvent;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use App\Http\Requests\Concerns\ValidatesProjectOwnership;

class StoreCalendarEventRequest extends FormRequest
{
    use ValidatesProjectOwnership;
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['nullable', 'exists:clients,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],

            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],

            'type' => ['required', Rule::in(CalendarEvent::TYPES)],
            'status' => ['required', Rule::in(CalendarEvent::STATUSES)],

            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],

            'is_all_day' => ['nullable', 'boolean'],

            'location' => ['nullable', 'string', 'max:255'],
            'meeting_provider' => ['nullable', 'in:none,nextcloud_talk,other'],
            'meeting_url' => ['nullable', 'required_if:meeting_provider,nextcloud_talk,other', 'url', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->withProjectOwnershipCheck($validator),
        ];
    }

    protected function prepareForValidation(): void
    {
        // REGOLA ARCHITETTURALE: Un evento deve sempre avere una data di fine.
        // Se l'utente omette end_at (evento istantaneo / task rapido), 
        // fallbackiamo su start_at per mantenere l'integrità del DB senza causare crash.
        $provider = $this->input('meeting_provider', 'none');
        $this->merge([
            'is_all_day' => filter_var($this->input('is_all_day', false), FILTER_VALIDATE_BOOLEAN),
            'end_at' => $this->input('end_at') ?: $this->input('start_at'),
            'meeting_provider' => $provider,
            'meeting_url' => $provider === 'none' ? null : $this->input('meeting_url'),
        ]);
    }
}