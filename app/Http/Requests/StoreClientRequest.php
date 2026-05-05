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

    protected function prepareForValidation(): void
    {
        if (! $this->has('status')) {
            $this->merge(['status' => 'active']);
        }
    }

    public function rules(): array
    {
        return [
            'name'             => ['required', 'string', 'max:255'],
            'company_name'     => ['nullable', 'string', 'max:255'],
            'email'            => ['nullable', 'email', 'max:255', 'unique:clients,email'],
            'phone'            => ['nullable', 'string', 'max:50'],
            'reference_person' => ['nullable', 'string', 'max:255'],
            'vat_number'       => ['nullable', 'string', 'max:20', 'unique:clients,vat_number'],
            'tax_code'         => ['nullable', 'string', 'max:20'],
            'address'          => ['nullable', 'string', 'max:255'],
            'city'             => ['nullable', 'string', 'max:100'],
            'postal_code'      => ['nullable', 'string', 'max:10'],
            'province'         => ['nullable', 'string', 'max:5'],
            'country'          => ['nullable', 'string', 'max:100'],
            'billing_email'    => ['nullable', 'email', 'max:255'],
            'pec'              => ['nullable', 'email', 'max:255'],
            'sdi_code'         => ['nullable', 'string', 'max:7'],
            'status'           => ['required', 'in:active,inactive'],
            'notes'            => ['nullable', 'string'],
            'activity_description' => ['nullable', 'string', 'max:2000'],
            'logo'             => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }
}
