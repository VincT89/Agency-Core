<?php

namespace App\Http\Requests\Api\V1\Integrations\N8n;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMarketingCampaignPostResultRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();
        if (isset($input['data']) && is_array($input['data'])) {
            $input = array_merge($input, $input['data']);
        }
        if (isset($input['body']) && is_array($input['body'])) {
            $input = array_merge($input, $input['body']);
        }

        $caption = $input['caption']
            ?? $input['text']
            ?? $input['description']
            ?? $input['copy']
            ?? null;

        $imageUrl = $input['image_url']
            ?? $input['media_url']
            ?? $input['url']
            ?? null;

        $imageUrls = $input['image_urls']
            ?? $input['images']
            ?? null;

        $hashtags = $input['hashtags'] ?? null;
        if (is_string($hashtags)) {
            $hashtags = preg_split('/[\s,]+/', trim($hashtags));
        }

        // Se n8n manda un singolo url come stringa in "images", convertiamolo
        if (is_string($imageUrls) && filter_var($imageUrls, FILTER_VALIDATE_URL)) {
            $imageUrls = [$imageUrls];
        }

        $merge = [];
        if ($caption !== null) $merge['caption'] = $caption;
        if ($imageUrl !== null) $merge['image_url'] = $imageUrl;
        if ($imageUrls !== null) $merge['image_urls'] = $imageUrls;
        if ($hashtags !== null) $merge['hashtags'] = $hashtags;

        // Se post_id, request_id o regeneration_type mancano a root ma ci sono in data
        if (isset($input['post_id'])) $merge['post_id'] = $input['post_id'];
        if (isset($input['request_id'])) $merge['request_id'] = $input['request_id'];
        if (isset($input['regeneration_type'])) $merge['regeneration_type'] = $input['regeneration_type'];

        if (!empty($merge)) {
            $this->merge($merge);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'post_id' => ['required', 'exists:marketing_campaign_posts,id'],
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
