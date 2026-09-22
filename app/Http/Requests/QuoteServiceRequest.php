<?php

namespace App\Http\Requests;

use App\Models\Quote;
use Illuminate\Foundation\Http\FormRequest;

class QuoteServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Quote::class);
    }

    public function rules(): array
    {
        return self::serviceRules();
    }

    public static function serviceRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:1500'],
            'description' => ['nullable', 'string', 'max:3000'],
            'delivery_terms' => ['nullable', 'string', 'max:500'],
            'delivery_summary' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:9999.99', 'regex:/^\d{1,4}(\.\d{1,2})?$/'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:999999.99', 'regex:/^\d{1,6}(\.\d{1,2})?$/'],
        ];
    }
}
