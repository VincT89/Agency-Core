<?php

namespace App\Http\Requests;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Client::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:clients,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'reference_person' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:20', 'unique:clients,vat_number'],
            'tax_code' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'province' => ['nullable', 'string', 'max:5'],
            'country' => ['nullable', 'string', 'max:100'],
            'country_code' => ['nullable', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'pec' => ['nullable', 'email', 'max:255'],
            'sdi_code' => ['nullable', 'string', 'min:6', 'max:7', 'regex:/^[A-Z0-9]+$/'],
            'status' => ['required', 'in:active,inactive'],
            'notes' => ['nullable', 'string'],
            'activity_description' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'nextcloud_folder_name' => [
                'required',
                'string',
                'max:100',
                'alpha_dash',
                'unique:clients,nextcloud_folder_name',
                'not_regex:/\.\./',
                'not_regex:/[\/\\\\]/',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->input('status', 'active'),
            'country_code' => filled($this->input('country_code'))
                ? strtoupper(trim((string) $this->input('country_code')))
                : null,
            'province' => filled($this->input('province'))
                ? strtoupper(trim((string) $this->input('province')))
                : null,
            'sdi_code' => filled($this->input('sdi_code'))
                ? strtoupper(trim((string) $this->input('sdi_code')))
                : null,
        ]);
    }
}
