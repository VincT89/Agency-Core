<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesTicketInput;
use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    use ValidatesTicketInput;

    public function authorize(): bool
    {
        return $this->user()->can('create', Ticket::class);
    }
}
