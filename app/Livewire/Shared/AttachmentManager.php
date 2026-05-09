<?php

namespace App\Livewire\Shared;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Database\Eloquent\Model;
use App\Models\Attachment;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Http\Requests\StoreAttachmentRequest;

class AttachmentManager extends Component
{
    use WithFileUploads, AuthorizesRequests;

    public Model $model;
    public $file;
    public $type;

    public function mount(Model $model)
    {
        $this->model = $model;
        
        $isValid = false;
        foreach (StoreAttachmentRequest::ATTACHABLE_MAP as $key => $class) {
            if ($this->model instanceof $class) {
                $isValid = true;
                $this->type = $key;
                break;
            }
        }

        if (!$isValid) {
            abort(403, 'Questo tipo di entità non supporta gli allegati.');
        }
    }

    public function updatedFile()
    {
        $this->upload();
    }

    public function upload()
    {
        $this->authorize('update', $this->model);
        
        $mimes = implode(',', StoreAttachmentRequest::ALLOWED_MIMES);
        $this->validate([
            'file' => "required|file|max:10240|mimes:{$mimes}"
        ]);

        $disk = 'attachments';
        $directory = $this->type . '/' . $this->model->id;

        $mimeType = $this->file->getClientMimeType();
        $extension = strtolower($this->file->getClientOriginalExtension());
        
        $attachmentType = 'other';
        if (str_starts_with($mimeType, 'image/')) {
            $attachmentType = 'image';
        } elseif (str_starts_with($mimeType, 'video/') || str_starts_with($mimeType, 'audio/')) {
            $attachmentType = 'media';
        } elseif (in_array($extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt'])) {
            $attachmentType = 'document';
        }

        $storedName = Str::uuid() . '.' . $extension;
        $path = $this->file->storeAs($directory, $storedName, $disk);

        $this->model->attachments()->create([
            'type' => $attachmentType,
            'uploaded_by' => auth()->id(),
            'disk' => $disk,
            'directory' => $directory,
            'path' => $path,
            'original_name' => $this->file->getClientOriginalName(),
            'stored_name' => $storedName,
            'mime_type' => $mimeType,
            'extension' => $extension,
            'size' => $this->file->getSize(),
        ]);

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
        return view('livewire.shared.attachment-manager');
    }
}
