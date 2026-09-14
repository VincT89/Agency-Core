<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreProjectFromQuoteRequest extends StoreProjectRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('createProject', $this->route('quote'));
    }

    public function rules(): array
    {
        return array_replace(parent::rules(), [
            'client_id' => ['required', 'integer', Rule::in([$this->route('quote')->client_id])],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('projects', 'code')->ignore($this->route('quote')->project_id)],
            'status' => ['required', Rule::in(['active', 'on_hold'])],
            'members.*' => ['integer', Rule::exists('users', 'id')->where('status', 'active')],
        ]);
    }
}
