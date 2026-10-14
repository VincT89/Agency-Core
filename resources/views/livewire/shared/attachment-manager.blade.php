<div class="u-mt-lg u-mb-lg">
    <x-panel title="Allegati" dot="var(--accent)">
        @if(count($model->attachments ?? []))
            <div class="attachments-table-region" x-data="{
                scrollable: false,
                observer: null,
                init() {
                    this.$nextTick(() => {
                        const scrollArea = this.$el.querySelector('.attachments-table-wrap');
                        const table = scrollArea?.querySelector('table');
                        if (!this.$el.isConnected || !scrollArea || !table) return;
                        this.observer = new ResizeObserver(() => {
                            this.scrollable = scrollArea.scrollWidth > scrollArea.clientWidth + 1;
                        });
                        this.observer.observe(scrollArea);
                        this.observer.observe(table);
                    });
                },
                destroy() { this.observer?.disconnect(); }
            }">
                <p id="attachment-scroll-help-{{ $this->getId() }}" class="attachments-scroll-hint" x-show="scrollable" x-cloak>Scorri orizzontalmente per vedere tutte le colonne.</p>
                <div class="attachments-table-wrap" x-ref="scrollArea" role="region" aria-label="Tabella allegati"
                     tabindex="0" :tabindex="scrollable ? 0 : -1"
                     :aria-describedby="scrollable ? 'attachment-scroll-help-{{ $this->getId() }}' : null">
                <table class="t-table attachments-table" x-ref="table">
                <thead>
                    <tr>
                        <th>Nome File</th>
                        <th>Tipo</th>
                        <th>Dimens.</th>
                        <th>Utente</th>
                        <th>Data</th>
                        <th class="t-actions">Azioni</th>
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
                        <td class="t-actions">
                            <div class="t-actions-list">
                                @can('download', $att)
                                <a href="{{ route('attachments.download', $att) }}" target="_blank" rel="noopener noreferrer"
                                   class="btn-ghost-primary btn-xs" title="Scarica" aria-label="Scarica allegato">
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
            </div>
        @else
            <div class="u-text-center u-text-muted u-p-md u-mb-md">Nessun allegato presente.</div>
        @endif

        @can($model instanceof \App\Models\Ticket || $model instanceof \App\Models\Quote ? 'addAttachment' : 'update', $model)
        <div class="attachments-upload-footer">
            @if($model instanceof \App\Models\Task)
                @php
                    $uploadOptions = [
                        'maxSelection' => \App\Http\Requests\StoreAttachmentRequest::TASK_MAX_SELECTION,
                        'maxBytes' => \App\Http\Requests\StoreAttachmentRequest::TASK_MAX_KILOBYTES * 1024,
                        'maxMegabytes' => \App\Http\Requests\StoreAttachmentRequest::TASK_MAX_KILOBYTES / 1024,
                        'extensions' => \App\Http\Requests\StoreAttachmentRequest::ALLOWED_MIMES,
                    ];
                @endphp
                <div x-data="taskAttachmentUploads(@js($uploadOptions))" wire:key="task-attachment-uploader-{{ $model->id }}">
                    <div class="attachments-upload-controls">
                        <input type="file" multiple x-ref="files" class="attachments-file-input"
                               accept="{{ '.'.implode(',.', $uploadOptions['extensions']) }}"
                               :disabled="busy" @change="select(Array.from($event.target.files)); $event.target.value = ''"
                               aria-label="Seleziona gli allegati da caricare">
                        <button type="button" class="btn btn-p" :disabled="busy" @click="$refs.files.click()"
                                aria-describedby="attachment-upload-help-{{ $this->getId() }}">Carica allegati</button>
                        <p id="attachment-upload-help-{{ $this->getId() }}" class="attachments-upload-help">
                            Fino a {{ $uploadOptions['maxSelection'] }} file per selezione, massimo {{ $uploadOptions['maxMegabytes'] }} MB ciascuno.
                            I file vengono caricati uno alla volta.
                        </p>
                    </div>
                    <p class="attachments-upload-error" role="alert" x-show="error" x-cloak x-text="error"></p>
                    <p class="attachments-upload-summary" role="status" aria-live="polite" x-show="items.length" x-cloak x-text="summary"></p>
                    <ul class="attachments-upload-queue" x-show="items.length" x-cloak aria-label="Esito del caricamento">
                        <template x-for="(item, index) in items" :key="index">
                            <li>
                                <span class="attachments-upload-name" x-text="item.name"></span>
                                <span class="attachments-upload-state" :class="{ 'attachments-upload-error': item.status === 'error' }" x-text="statusText(item)"></span>
                                <progress x-show="item.status === 'uploading' || item.status === 'saving'" max="100" :value="item.progress" :aria-label="'Caricamento di ' + item.name"></progress>
                                <p class="attachments-upload-error" x-show="item.error" x-text="item.error"></p>
                            </li>
                        </template>
                    </ul>
                </div>
            @else
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
            @endif
        </div>
        @endcan
    </x-panel>
</div>
