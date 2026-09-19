<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientMaterialRequest;
use App\Models\Attachment;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class ClientMaterialController extends Controller
{
    public function index(Request $request, Client $client)
    {
        $this->authorize('viewMaterials', $client);
        $filters = $request->validate(['search' => 'nullable|string|max:255', 'category' => ['nullable', Rule::in(array_keys(Attachment::CLIENT_MATERIAL_CATEGORIES))]]);
        $materials = $client->attachments()->whereNotNull('client_material_category')->with('uploader:id,name')
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('client_material_category', $category))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($match) => $match->where('original_name', 'like', '%'.$search.'%')->orWhere('description', 'like', '%'.$search.'%')))
            ->latest()->orderByDesc('id')->paginate(20)->withQueryString();

        return view('clients.materials', compact('client', 'materials', 'filters'));
    }

    public function store(StoreClientMaterialRequest $request, Client $client)
    {
        $data = $request->validated();
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $directory = 'client/'.$client->id.'/materials';
        $name = Str::uuid().'.'.$extension;
        $path = $file->storeAs($directory, $name, 'attachments');
        if (! $path) {
            throw new RuntimeException('Impossibile salvare il materiale.');
        }
        try {
            $client->attachments()->create([
                'type' => 'document', 'client_material_category' => $data['category'], 'description' => $data['description'] ?? null,
                'uploaded_by' => $request->user()->id, 'disk' => 'attachments', 'directory' => $directory, 'path' => $path,
                'stored_name' => $name, 'original_name' => $file->getClientOriginalName(), 'extension' => $extension,
                'mime_type' => 'application/octet-stream', 'size' => $file->getSize(),
            ]);
        } catch (Throwable $exception) {
            Storage::disk('attachments')->delete($path);
            throw $exception;
        }

        return redirect()->route('clients.materials.index', $client)->with('success', 'Materiale aggiunto all’archivio del cliente.');
    }
}
