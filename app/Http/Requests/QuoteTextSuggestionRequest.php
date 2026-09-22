<?php

namespace App\Http\Requests;

use App\Models\Quote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class QuoteTextSuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Quote::class);
    }

    public function rules(): array
    {
        return [
            'introduction' => ['nullable', 'string', 'max:10000'],
            'instructions' => ['nullable', 'string', 'max:1500'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*' => ['required', 'array:name,summary,description'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.summary' => ['nullable', 'string', 'max:1500'],
            'items.*.description' => ['nullable', 'string', 'max:3000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if (strlen(json_encode($this->only('introduction', 'instructions', 'items'), JSON_UNESCAPED_UNICODE)) > 40000) {
                $validator->errors()->add('items', 'Il testo è troppo lungo per una singola richiesta AI. Riduci le descrizioni prima di riprovare.');
            }
        }];
    }
}
