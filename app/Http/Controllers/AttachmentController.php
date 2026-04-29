<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttachmentRequest;
use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function store(StoreAttachmentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $attachable = $this->resolveAttachable(
            $data['attachable_type'],
            (int) $data['attachable_id']
        );

        if (! $attachable) {
            abort(404);
        }



        $file = $request->file('file');

        $disk = 'attachments';
        $directory = $this->buildDirectory($data['attachable_type'], $attachable->getKey());

        $storedName = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs($directory, $storedName, $disk);

        $attachment = $attachable->attachments()->create([
            'type' => $data['type'],
            'uploaded_by' => auth()->id(),
            'disk' => $disk,
            'directory' => $directory,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $storedName,
            'mime_type' => $file->getClientMimeType(),
            'extension' => $file->getClientOriginalExtension(),
            'size' => $file->getSize(),
            'description' => $data['description'] ?? null,
        ]);

        return back()->with('success', 'Allegato caricato correttamente.');
    }

    public function download(Attachment $attachment): StreamedResponse
    {
        $this->authorize('download', $attachment);

        $disk = Storage::disk($attachment->disk);

        if (! $disk->exists($attachment->path)) {
            abort(404);
        }

        return $disk->download($attachment->path, $attachment->original_name);
    }

    public function destroy(Attachment $attachment): RedirectResponse
    {
        $this->authorize('delete', $attachment);
        $attachment->delete();

        return back()->with('success', 'Allegato eliminato correttamente.');
    }

    protected function resolveAttachable(string $type, int $id): ?Model
    {
        $map = StoreAttachmentRequest::ATTACHABLE_MAP;
        $modelClass = $map[$type] ?? null;

        if (! $modelClass) {
            return null;
        }

        return $modelClass::query()->find($id);
    }

    protected function buildDirectory(string $type, int $id): string
    {
        return $type . '/' . $id;
    }
}