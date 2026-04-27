@props([
    'model'
])

@php
    $type = array_search(get_class($model), \App\Http\Requests\StoreAttachmentRequest::ATTACHABLE_MAP);
@endphp

@if($type)
<div style="margin-top:20px;margin-bottom:20px;">
    <x-panel title="Allegati" dot="var(--accent)" padded>
        @if(count($model->attachments ?? []))
            <table class="t-table" style="margin-bottom:16px">
                <thead>
                    <tr>
                        <th>Nome File</th>
                        <th>Tipo</th>
                        <th>Dimens.</th>
                        <th>Utente</th>
                        <th>Data</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($model->attachments as $att)
                    <tr>
                        <td class="name-col">{{ $att->original_name }}</td>
                        <td class="mono-col">{{ strtoupper($att->type ?? 'DOCUMENT') }}</td>
                        <td class="mono-col">{{ $att->mime_type }}</td>
                        <td class="mono-col">{{ number_format($att->size / 1024, 0) }} KB</td>
                        <td>{{ $att->uploader?->name ?? 'Sistema' }}</td>
                        <td class="mono-col">{{ $att->created_at->format('d/m/Y H:i') }}</td>
                        <td>
                            <div style="display:flex;gap:8px">
                                @can('download', $att)
                                <a href="{{ route('attachments.download', $att) }}" target="_blank" class="btn-icon" title="Scarica">↓</a>
                                @endcan

                                @can('delete', $att)
                                    <x-delete-modal 
                                        action="{{ route('attachments.destroy', $att) }}" 
                                        title="Elimina Allegato" 
                                        message="Sei sicuro di voler eliminare il file '{{ $att->original_name }}'?">
                                        <button type="button" class="btn-icon" style="color:var(--red)" title="Elimina">×</button>
                                    </x-delete-modal>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div style="text-align:center;color:var(--text3);padding:16px;margin-bottom:16px;">Nessun allegato presente.</div>
        @endif

        @can('update', $model)
        @can('create', App\Models\Attachment::class)
        <div style="border-top:1px solid var(--line);padding-top:16px;">
            <form action="{{ route('attachments.store') }}" method="POST" enctype="multipart/form-data" style="display:flex;gap:16px;align-items:flex-end">
                @csrf
                <input type="hidden" name="attachable_type" value="{{ $type }}">
                <input type="hidden" name="attachable_id" value="{{ $model->id }}">
                <div style="flex:1">
                    <div class="form-lbl">Tipo File</div>
                    <select name="type" class="form-in" required style="padding:4px 8px;font-size:12px;height:28px">
                        <option value="document">Documento</option>
                        <option value="image">Immagine</option>
                        <option value="media">Media (Audio/Video)</option>
                        <option value="other">Altro</option>
                    </select>
                </div>
                <div style="flex:1">
                    <div class="form-lbl">Nuovo Allegato</div>
                    <input type="file" name="file" class="form-in" required style="padding:4px 8px;font-size:12px;height:28px">
                </div>
                <button type="submit" class="btn btn-p" style="padding:6px 12px; height:28px">Carica →</button>
            </form>
            @error('file')
                <div style="color:var(--red);font-size:12px;margin-top:4px">{{ $message }}</div>
            @enderror
            @error('attachable_type')
                <div style="color:var(--red);font-size:12px;margin-top:4px">{{ $message }}</div>
            @enderror
        </div>
        @endcan
        @endcan
    </x-panel>
</div>
@endif
