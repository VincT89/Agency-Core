<?php

namespace App\Http\Requests;

use App\Models\Quote;
use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('quote') instanceof Quote
            ? $this->user()->can('update', $this->route('quote'))
            : $this->user()->can('create', Quote::class);
    }

    public function rules(): array
    {
        $editing = $this->route('quote') instanceof Quote;

        return [
            'client_id' => $editing ? ['missing'] : ['required', 'integer', 'exists:clients,id'],
            'ticket_id' => $editing ? ['missing'] : ['nullable', 'integer', 'exists:tickets,id'],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*' => ['required', 'array:name,description,quantity,unit_price'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:3000'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:9999.99', 'regex:/^\d{1,4}(\.\d{1,2})?$/'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999.99', 'regex:/^\d{1,6}(\.\d{1,2})?$/'],
            'status' => ['missing'], 'total' => ['missing'], 'created_by' => ['missing'],
            'client_snapshot' => ['missing'], 'project_id' => ['missing'], 'previous_quote_id' => ['missing'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty() || ! $this->filled('ticket_id')) {
                return;
            }
            $ticket = Ticket::find($this->integer('ticket_id'));
            if (! $ticket || $ticket->type !== 'quote' || (int) $ticket->client_id !== $this->integer('client_id')
                || ! $this->user()->can('view', $ticket)) {
                $validator->errors()->add('ticket_id', 'Seleziona una richiesta di preventivo appartenente al cliente.');
            }
        }];
    }
}
