<div class="u-mt-lg u-mb-lg">
    <x-panel title="Allegati" dot="var(--accent)">
        @if(count($model->attachments ?? []))
            <div class="attachments-table-wrap">
                <table class="t-table attachments-table">
                <thead>
                    <tr>
                        <th>Nome File</th>
                        <th>Tipo</th>
                        <th>Dimens.</th>
                        <th>Utente</th>
                        <th>Data</th>
                        <th class="u-text-right">Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($model->attachments as $att)
                    <tr>
                        <td class="name-col">{{ $att->original_name }}</td>
                        <td class="mono-col">{{ strtoupper($att->type ?? 'DOCUMENT') }}</td>
                        <td class="mono-col">{{ number_format($att->size / 1024, 0) }} KB</td>
                        <td>{{ $att->uploader?->name ?? 'Sistema' }}</td>
                        <td class="mono-col">{{ $att->created_at->format('d/m/Y H:i') }}</td>
                        <td class="u-text-right">
                            <div class="u-flex-end u-gap-sm">
                                @can('download', $att)
                                <a href="{{ route('attachments.download', $att) }}" target="_blank" class="btn-ghost-primary btn-xs" title="Scarica">
                                    <i data-lucide="download" class="u-icon-sm"></i>
                                </a>
                                @endcan

                                @can('delete', $att)
                                    <x-confirm-modal
                                        title="Elimina Allegato"
                                        message="Sei sicuro di voler eliminare il file '{{ $att->original_name }}'?"
                                        confirmText="Elimina"
                                        confirmMethod="deleteAttachment({{ $att->id }})"
                                        confirmClass="btn btn-p btn-danger"
                                        icon="trash-2"
                                        variant="danger"
                                    >
                                        <button type="button" class="btn-ghost-danger btn-xs" title="Elimina">
                                            <i data-lucide="trash-2" class="u-icon-sm"></i>
                                        </button>
                                    </x-confirm-modal>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @else
            <div class="u-text-center u-text-muted u-p-md u-mb-md">Nessun allegato presente.</div>
        @endif

        @can('update', $model)
        <div class="attachments-upload-footer">
            <div class="u-flex-between">
                <div>
                    <input
                        id="attachment-upload-{{ $model->id }}"
                        type="file"
                        wire:model="file"
                        class="attachments-file-input"
                    />
                    
                    <label for="attachment-upload-{{ $model->id }}" class="btn btn-p attachments-upload-label">
                        <span wire:loading.remove wire:target="file">Carica allegato</span>
                        <span wire:loading wire:target="file">Caricamento...</span>
                    </label>
                </div>
                
                @error('file')
                    <div class="u-text-red u-text-meta">{{ $message }}</div>
                @enderror
            </div>
        </div>
        @endcan
    </x-panel>
</div>
