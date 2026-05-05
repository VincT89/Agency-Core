<?php

namespace App\Http\Requests\Integrations\N8n;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateTicketFromN8nRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['nullable', 'integer', 'exists:clients,id', 'required_without:project_id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id', 'required_without:client_id'],

            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],

            'priority' => ['nullable', 'string', 'in:low,medium,high,urgent'],
            'source' => ['required', 'string', 'in:whatsapp,n8n,email,manual'],
            'n8n_execution_id' => ['required', 'string', 'max:255'],

            'context' => ['nullable', 'array'],
        ];
    }

    public function after(): array
    {
        return [
            function (\Illuminate\Validation\Validator $validator) {
                $clientId = $this->input('client_id');
                $projectId = $this->input('project_id');

                if ($clientId && $projectId) {
                    $project = \App\Models\Project::withoutGlobalScope(\App\Models\Scopes\ProjectSupremacyScope::class)->find($projectId);
                    if ($project && $project->client_id != $clientId) {
                        $validator->errors()->add('client_id', 'Il client_id fornito non corrisponde al project_id.');
                    }
                }
            }
        ];
    }
}
