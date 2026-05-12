<?php

namespace App\Http\Requests\Api\V1\Integrations\N8n;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMarketingCampaignPostVersionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'request_id' => ['required', 'string'],
            'external_generation_id' => ['nullable', 'string'],
            'regeneration_type' => ['required', 'in:full,caption,image'],
            'title' => ['nullable', 'string'],
            'caption' => ['required_if:regeneration_type,full,caption', 'string', 'nullable'],
            'hashtags' => ['nullable', 'array'],
            'image_url' => ['nullable', 'url'],
            'image_urls' => ['nullable', 'array'],
            'image_urls.*' => ['required', 'url'],
            'prompt_used' => ['nullable', 'string'],
            'raw_payload' => ['nullable', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = $this->input('regeneration_type');

            if (in_array($type, ['full', 'image'], true)) {
                $hasImageUrl = filled($this->input('image_url'));
                $hasImageUrls = is_array($this->input('image_urls'))
                    && count($this->input('image_urls')) > 0;

                if (! $hasImageUrl && ! $hasImageUrls) {
                    $validator->errors()->add(
                        'image_url',
                        'Per regeneration_type full o image è richiesta image_url oppure image_urls.'
                    );
                }
            }
        });
    }
}
