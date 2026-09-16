<?php

namespace App\Http\Requests\Concerns;

use App\Models\Client;
use Illuminate\Validation\Rule;

trait ValidatesClientRegistry
{
    protected function clientRegistryRules(?Client $client = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('clients', 'email')->ignore($client)],
            'phone' => ['nullable', 'string', 'max:50'],
            'reference_person' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:20', Rule::unique('clients', 'vat_number')->ignore($client)],
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
            'commercial_notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    protected function prepareClientRegistryData(): void
    {
        $normalized = [];
        foreach (['email', 'billing_email', 'pec', 'vat_number', 'tax_code', 'country_code', 'province', 'sdi_code'] as $field) {
            $value = $this->input($field);
            if (! $this->exists($field) || ! is_string($value)) {
                continue;
            }

            $value = trim($value);
            $normalized[$field] = $value === '' ? null : (
                in_array($field, ['email', 'billing_email', 'pec'], true) ? strtolower($value) : strtoupper($value)
            );
        }
        $this->merge($normalized);
    }

    public function messages(): array
    {
        $duplicate = 'Già presente in anagrafica. Cerca il cliente oppure chiedi all’amministratore di associartelo.';

        return [
            'email.unique' => 'Email già utilizzata. '.$duplicate,
            'vat_number.unique' => 'Partita IVA già utilizzata. '.$duplicate,
            'commercial_user_id.missing' => 'Il commerciale viene associato automaticamente. Le riassegnazioni sono gestite dall’amministratore.',
        ];
    }
}
