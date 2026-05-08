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
            'image_url' => ['required_if:regeneration_type,full,image', 'url', 'nullable'],
            'prompt_used' => ['nullable', 'string'],
            'raw_payload' => ['nullable', 'array'],
        ];
    }
}
