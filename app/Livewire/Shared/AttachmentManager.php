<?php

namespace App\Livewire\Shared;

use App\Http\Requests\StoreAttachmentRequest;
use App\Models\Quote;
use App\Models\Task;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;
use Throwable;

class AttachmentManager extends Component
{
    use AuthorizesRequests, WithFileUploads;

    #[Locked]
    public Model $model;

    public $file;

    #[Locked]
    public $type;

    public function mount(Model $model)
    {
        abort_if(auth()->user()->isCommercial() && ! $model instanceof Ticket && ! $model instanceof Quote, 403);
        if ($model instanceof Ticket || $model instanceof Quote) {
            $this->authorize('view', $model);
        }
        $this->model = $model;

        $isValid = false;
        foreach (StoreAttachmentRequest::ATTACHABLE_MAP as $key => $class) {
            if ($this->model instanceof $class) {
                $isValid = true;
                $this->type = $key;
                break;
            }
        }

        if (! $isValid) {
            abort(403, 'Questo tipo di entità non supporta gli allegati.');
        }
    }

    public function updatedFile()
    {
        // Task files are transferred and saved one at a time by the upload queue.
        if (! $this->model instanceof Task) {
            $this->upload();
        }
    }

    public function saveTaskAttachment(): array
    {
        abort_unless($this->model instanceof Task, 403);
        $this->authorize('update', $this->model);

        try {
            $this->upload();

            return ['saved' => true];
        } catch (ValidationException $exception) {
            return ['saved' => false, 'message' => collect($exception->errors())->flatten()->first()];
        } catch (Throwable $exception) {
            report($exception);

            return ['saved' => false, 'message' => 'Non è stato possibile salvare il file. Riprova.'];
        } finally {
            $this->reset('file');
        }
    }

    public function upload()
    {
        $this->authorize($this->model instanceof Ticket || $this->model instanceof Quote ? 'addAttachment' : 'update', $this->model);

        $this->resetValidation('file');
        $maxKilobytes = StoreAttachmentRequest::maxKilobytesFor($this->type);
        $maxMegabytes = $maxKilobytes / 1024;
        $this->validate([
            'file' => StoreAttachmentRequest::fileRulesFor($this->type),
        ], [
            'file.required' => 'Seleziona un file da caricare.',
            'file.file' => 'Il file selezionato non è valido.',
            'file.uploaded' => 'Il trasferimento del file non è riuscito. Riprova.',
            'file.max' => "Il file supera il limite di {$maxMegabytes} MB.",
            'file.mimes' => 'Formato non riconosciuto. Sono ammessi PDF, immagini, Word, Excel, CSV, ZIP e TXT.',
        ]);

        $disk = 'attachments';
        $directory = $this->type.'/'.$this->model->id;

        $mimeType = $this->file->getMimeType();
        $extension = strtolower($this->file->getClientOriginalExtension());
        $originalName = $this->file->getClientOriginalName();
        $size = $this->file->getSize();

        $attachmentType = 'other';
        if (str_starts_with($mimeType, 'image/')) {
            $attachmentType = 'image';
        } elseif (str_starts_with($mimeType, 'video/') || str_starts_with($mimeType, 'audio/')) {
            $attachmentType = 'media';
        } elseif (in_array($extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt'])) {
            $attachmentType = 'document';
        }

        $storedName = Str::uuid().'.'.$extension;
        $path = null;

        try {
            DB::transaction(function () use ($directory, $storedName, $disk, $attachmentType, $originalName, $mimeType, $extension, $size, &$path) {
                $path = $this->file->storeAs($directory, $storedName, $disk);
                if (! $path || ! Storage::disk($disk)->exists($path)) {
                    throw new RuntimeException('Attachment storage failed.');
                }

                $this->model->attachments()->create([
                    'type' => $attachmentType,
                    'uploaded_by' => auth()->id(),
                    'disk' => $disk,
                    'directory' => $directory,
                    'path' => $path,
                    'original_name' => $originalName,
                    'stored_name' => $storedName,
                    'mime_type' => $mimeType,
                    'extension' => $extension,
                    'size' => $size,
                ]);
            });
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk($disk)->delete($path);
            }

            throw $exception;
        }

        $this->reset('file');

        $this->model->load('attachments.uploader');
    }

    public function deleteAttachment($id)
    {
        $attachment = $this->model->attachments()->whereKey($id)->firstOrFail();
        $this->authorize('delete', $attachment);

        $attachment->delete();

        $this->model->load('attachments.uploader');
    }

    public function render()
    {
        abort_if(auth()->user()->isCommercial() && ! $this->model instanceof Ticket && ! $this->model instanceof Quote, 403);
        if ($this->model instanceof Ticket || $this->model instanceof Quote) {
            $this->authorize('view', $this->model);
        }

        return view('livewire.shared.attachment-manager');
    }
}
