<?php

namespace App\Http\Requests;

use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Task;
use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAttachmentRequest extends FormRequest
{
    public const ALLOWED_MIMES = [
        'pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp',
        'doc', 'docx', 'xls', 'xlsx', 'csv',
        'zip', 'txt',
    ];

    public const ATTACHABLE_MAP = [
        'client' => Client::class,
        'project' => Project::class,
        'task' => Task::class,
        'ticket' => Ticket::class,
        'calendar_event' => CalendarEvent::class,
        'invoice' => Invoice::class,
        'payment' => Payment::class,
    ];

    public function authorize(): bool
    {
        $type = $this->input('attachable_type');
        $id = $this->input('attachable_id');

        $modelClass = self::ATTACHABLE_MAP[$type] ?? null;

        if (! $modelClass) {
            return false;
        }

        $attachable = $modelClass::query()->find($id);

        if (! $attachable) {
            return false;
        }

        // Intentionally resolving attachment capabilities by checking parent update rights
        return $this->user()->can('update', $attachable);
    }

    public function rules(): array
    {
        return [
            'attachable_type' => ['required', 'string'],
            'attachable_id' => ['required', 'integer'],
            'type' => ['required', 'string', 'in:document,image,media,other'],
            'file' => [
                'required',
                'file',
                'max:10240',
                'mimes:' . implode(',', self::ALLOWED_MIMES),
            ],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $type = $this->input('attachable_type');
                $id = $this->input('attachable_id');

                $modelClass = self::ATTACHABLE_MAP[$type] ?? null;

                if (! $modelClass) {
                    $validator->errors()->add(
                        'attachable_type',
                        'Il tipo di allegato selezionato non è valido.'
                    );

                    return;
                }

                $exists = $modelClass::query()->whereKey($id)->exists();

                if (! $exists) {
                    $validator->errors()->add(
                        'attachable_id',
                        'L\'entità selezionata non esiste.'
                    );
                }
            },
        ];
    }
}