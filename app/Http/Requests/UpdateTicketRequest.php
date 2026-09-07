<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesTicketInput;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketRequest extends FormRequest
{
    use ValidatesTicketInput;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('ticket'));
    }
}
