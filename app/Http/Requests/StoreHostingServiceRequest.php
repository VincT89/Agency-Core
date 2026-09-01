<?php

namespace App\Http\Requests;

use App\Models\HostingService;
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
        return $this->user()->can('create', HostingService::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function prepareForValidation(): void
    {
        $data = [];

        if (! $this->filled('status')) {
            $data['status'] = 'active';
        }

        if (! $this->has('service_types') && $this->filled('type')) {
            $data['service_types'] = [$this->input('type')];
        } elseif (is_array($this->input('service_types')) && $this->input('service_types') !== []) {
            $serviceTypes = array_values($this->input('service_types'));
            $data['type'] = $serviceTypes[0];
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'exists:clients,id'],
            'type' => ['required', Rule::in(array_keys(HostingService::TYPE_LABELS))],
            'service_types' => ['required', 'array', 'min:1', 'max:'.count(HostingService::TYPE_LABELS)],
            'service_types.*' => ['required', 'string', 'distinct', Rule::in(array_keys(HostingService::TYPE_LABELS))],
            'name' => ['required', 'string', 'max:255'],
            'domain' => [Rule::requiredIf(in_array('domain', (array) $this->input('service_types'), true)), 'nullable', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'suspended', 'cancelled'])],
            'access_url' => ['nullable', 'url', 'max:255'],
            'username' => [
                Rule::prohibitedIf(! $this->user()->can('manageCredentials', HostingService::class)),
                'nullable',
                'string',
                'max:255',
            ],
            'password' => [
                Rule::prohibitedIf(! $this->user()->can('manageCredentials', HostingService::class)),
                'nullable',
                'string',
            ],
            'renewal_date' => ['nullable', 'date'],
            'renewal_cost' => [
                Rule::prohibitedIf(! $this->user()->canAccessFinance()),
                'nullable',
                'numeric',
                'min:0',
            ],
            'resource_cost' => [
                Rule::prohibitedIf(! $this->user()->canAccessFinance()),
                'nullable',
                'numeric',
                'min:0',
            ],
            'billing_cycle' => ['nullable', Rule::in(['monthly', 'yearly', 'one_time', 'other'])],
            'notes' => ['nullable', 'string'],
            'context' => ['nullable', Rule::in(['domain', 'hosting'])],
        ];
    }
}
