<?php

namespace App\Http\Requests;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    use Concerns\ValidatesClientCommercial;
    use Concerns\ValidatesClientRegistry;

    public function authorize(): bool
    {
        return $this->user()->can('create', Client::class);
    }

    public function rules(): array
    {
        $commercial = $this->user()->isCommercial();

        return array_merge($this->clientRegistryRules(), [
            'commercial_user_id' => $this->commercialUserRules(),
            'status' => $commercial ? ['missing'] : ['required', 'in:active,inactive'],
            'notes' => $commercial ? ['missing'] : ['nullable', 'string'],
            'activity_description' => $commercial ? ['missing'] : ['nullable', 'string', 'max:2000'],
            'logo' => $commercial ? ['missing'] : ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'nextcloud_folder_name' => $commercial ? ['missing'] : [
                'nullable', 'string', 'max:100', 'alpha_dash',
                'unique:clients,nextcloud_folder_name',
                'not_regex:/\.\./', 'not_regex:/[\/\\\\]/',
            ],
        ]);
    }

    protected function prepareForValidation(): void
    {
        $this->prepareClientRegistryData();

        if (! $this->user()->isCommercial()) {
            $this->merge(['status' => $this->input('status', 'active')]);
        }
    }
}
