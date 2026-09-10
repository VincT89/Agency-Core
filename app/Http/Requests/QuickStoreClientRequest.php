<?php

namespace App\Http\Requests;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;

class QuickStoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quickCreate', Client::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:clients,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'vat_number' => ['nullable', 'string', 'max:20', 'unique:clients,vat_number'],
            'address' => ['nullable', 'string', 'max:255'],
            'commercial_user_id' => ['missing'],
        ];
    }

    public function messages(): array
    {
        $duplicate = 'Già presente in anagrafica. Cerca il cliente oppure chiedi all’amministratore di associartelo.';

        return [
            'email.unique' => 'Email già utilizzata. '.$duplicate,
            'vat_number.unique' => 'Partita IVA già utilizzata. '.$duplicate,
            'commercial_user_id.missing' => 'Il commerciale viene associato automaticamente.',
        ];
    }
}
