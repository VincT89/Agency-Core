<?php

namespace App\Http\Requests;

use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Expense;
use App\Models\HostingService;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Task;
use App\Models\Ticket;
use App\Rules\TaskAttachmentFileType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAttachmentRequest extends FormRequest
{
    public const TASK_MAX_KILOBYTES = 15 * 1024;

    public const TASK_MAX_SELECTION = 10;

    public static function maxKilobytesFor(string $type): int
    {
        return $type === 'task' ? self::TASK_MAX_KILOBYTES : 10 * 1024;
    }

    public static function fileRulesFor(string $type): array
    {
        return [
            'bail',
            'required',
            'file',
            'max:'.self::maxKilobytesFor($type),
            $type === 'task' ? new TaskAttachmentFileType(self::ALLOWED_MIMES) : 'mimes:'.implode(',', self::ALLOWED_MIMES),
        ];
    }

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
        'quote' => Quote::class,
        'payment' => Payment::class,
        'expense' => Expense::class,
        'hosting_service' => HostingService::class,
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
        return $this->user()->can($attachable instanceof Ticket || $attachable instanceof Quote ? 'addAttachment' : 'update', $attachable);
    }

    public function rules(): array
    {
        return [
            'attachable_type' => ['required', 'string'],
            'attachable_id' => ['required', 'integer'],
            'type' => ['required', 'string', 'in:document,image,media,other'],
            'file' => self::fileRulesFor($this->input('attachable_type', '')),
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
