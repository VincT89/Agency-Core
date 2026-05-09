<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHostingServiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\HostingService::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'exists:clients,id'],
            'type' => ['required', Rule::in(['domain', 'hosting', 'website', 'maintenance', 'email', 'dns', 'other'])],
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'suspended', 'cancelled'])],
            'access_url' => ['nullable', 'url', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string'],
            'renewal_date' => ['nullable', 'date'],
            'renewal_cost' => ['nullable', 'numeric', 'min:0'],
            'resource_cost' => ['nullable', 'numeric', 'min:0'],
            'billing_cycle' => ['nullable', Rule::in(['monthly', 'yearly', 'one_time', 'other'])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
