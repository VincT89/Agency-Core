<?php

namespace App\Http\Requests;

use App\Domain\Finance\Support\ItalianTaxIdentifier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateBillingProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessFinance() === true;
    }

    public function rules(): array
    {
        return [
            'legal_name' => ['required', 'string', 'max:255'],
            'vat_country_code' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'vat_number' => ['required', 'string', 'max:28'],
            'tax_code' => ['nullable', 'string', 'max:28'],
            'fiscal_regime' => ['required', 'string', 'size:4', 'regex:/^RF\d{2}$/'],
            'address' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:12'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['nullable', 'required_if:country_code,IT', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'country_code' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'pec' => ['nullable', 'email', 'max:255'],
            'recipient_code' => ['nullable', 'string', 'min:6', 'max:7', 'regex:/^[A-Z0-9]+$/'],
            'iban' => ['nullable', 'string', 'min:15', 'max:34', 'regex:/^[A-Z]{2}\d{2}[A-Z0-9]+$/'],
            'invoice_series' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9-]+$/'],
            'initial_sequence' => ['required', 'integer', 'min:1', 'max:999999'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (
                    $this->input('vat_country_code') === 'IT'
                    && ! ItalianTaxIdentifier::isValidVatNumber($this->input('vat_number'))
                ) {
                    $validator->errors()->add(
                        'vat_number',
                        'La partita IVA italiana non è formalmente valida.'
                    );
                }

                if (
                    $this->input('country_code') === 'IT'
                    && filled($this->input('tax_code'))
                    && ! ItalianTaxIdentifier::isValidTaxCode($this->input('tax_code'))
                ) {
                    $validator->errors()->add(
                        'tax_code',
                        'Il codice fiscale non è formalmente valido.'
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $uppercaseFields = [
            'vat_country_code',
            'vat_number',
            'tax_code',
            'fiscal_regime',
            'province',
            'country_code',
            'recipient_code',
            'iban',
            'invoice_series',
        ];

        $normalized = [];

        foreach ($uppercaseFields as $field) {
            $value = $this->input($field);
            $normalized[$field] = filled($value)
                ? strtoupper(preg_replace('/\s+/', '', trim((string) $value)) ?? '')
                : null;
        }

        $this->merge($normalized);
    }
}
