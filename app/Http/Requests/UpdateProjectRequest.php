<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }

    public function rules(): array
    {
        $sharedContainer = $this->route('project')->isInvoiceContainer();

        return [
            'client_id'   => $sharedContainer ? ['prohibited'] : ['nullable', 'exists:clients,id'],
            'name'        => ['required', 'string', 'max:255'],
            'code'        => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'status'      => $sharedContainer ? ['required', 'in:active'] : ['required', 'string', 'max:50'],
            'start_date'  => ['nullable', 'date'],
            'end_date'    => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes'       => ['nullable', 'string'],
            'members'     => ['required', 'array', 'min:1'],
            'members.*'   => ['integer', 'exists:users,id'],
            'roles'       => ['nullable', 'array'],
            'roles.*'     => ['nullable', 'string', 'in:member,lead,sponsor'],
        ];
    }
}
