<x-app-layout title="{{ $calendarEvent->title }}">
    <div class="u-mb-lg u-flex-end">
        <a href="{{ route('calendar-events.index') }}" class="btn btn-g u-flex-center u-gap-xs">
            <i data-lucide="arrow-left" class="u-icon-sm"></i> Torna al calendario
        </a>
    </div>
    <x-page-header
        eyebrow="Dettaglio · Evento"
        
    >
    <x-slot:title><strong>{{ $calendarEvent->title }}</strong></x-slot:title>
        <x-slot:actions>
            <x-badge :status="$calendarEvent->status" :label="$calendarEvent->status_label" />
            @can('update', $calendarEvent)
                <a href="{{ route('calendar-events.edit', $calendarEvent) }}" class="btn btn-g">Modifica</a>
            @endcan
        
            @can('delete', $calendarEvent)
                <form action="{{ route('calendar-events.destroy', $calendarEvent) }}" method="POST"
                      onsubmit="return confirm('Eliminare l\'evento {{ addslashes($calendarEvent->title) }}?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-g cal-btn-danger">
                        Elimina
                    </button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="g-2col">
        <x-panel title="Dettagli Principali" dot="var(--blue)" padded>
            <div class="form-g mb-2">
                <div class="form-lbl">Inizio</div>
                <div class="cal-detail-value-mono">
                    {{ $calendarEvent->start_at?->isoFormat('D MMMM YYYY - HH:mm') }}
                    @if($calendarEvent->is_all_day)
                        <span class="cal-time-badge">Tutto il giorno</span>
                    @endif
                </div>
            </div>
            
            @if($calendarEvent->end_at)
            <div class="form-g mb-2">
                <div class="form-lbl">Fine</div>
                <div class="cal-detail-value-mono">{{ $calendarEvent->end_at->isoFormat('D MMMM YYYY - HH:mm') }}</div>
            </div>
            @endif

            <div class="form-g mb-2">
                <div class="form-lbl">Tipo</div>
                <div><x-badge :status="$calendarEvent->type" :label="$calendarEvent->type_label" /></div>
            </div>

            @if($calendarEvent->location)
            <div class="form-g mb-2">
                <div class="form-lbl">Luogo Fisico</div>
                <div class="cal-detail-value">{{ $calendarEvent->location }}</div>
            </div>
            @endif

            @if($calendarEvent->meeting_url)
            <div class="form-g">
                <div class="form-lbl">Videochiamata ({{ $calendarEvent->meeting_provider === 'nextcloud_talk' ? 'Nextcloud Talk' : 'Altro' }})</div>
                <div class="u-mt-xs">
                    <a href="{{ $calendarEvent->meeting_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-p btn-sm u-flex-center u-gap-xs">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-video"><path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5"/><rect x="2" y="6" width="14" height="12" rx="2"/></svg>
                        Accedi alla call
                    </a>
                </div>
            </div>
            @endif

            @if($calendarEvent->description)
            <div class="form-g u-mt-md">
                <div class="form-lbl">Descrizione</div>
                <div class="cal-detail-desc">{{ $calendarEvent->description }}</div>
            </div>
            @endif
        </x-panel>

        <x-panel title="Collegamento & Team" dot="var(--teal)" padded>
            <div class="form-g mb-2">
                <div class="form-lbl">Cliente</div>
                <div class="cal-detail-value">
                    @if($calendarEvent->client)
                        <a href="{{ route('clients.show', $calendarEvent->client) }}" class="cal-link-accent">{{ $calendarEvent->client->name }}</a>
                    @else
                        —
                    @endif
                </div>
            </div>
            <div class="form-g mb-2">
                <div class="form-lbl">Progetto</div>
                <div class="cal-detail-value">
                    @if($calendarEvent->project)
                        <a href="{{ route('projects.show', $calendarEvent->project) }}" class="cal-link-accent">{{ $calendarEvent->project->name }}</a>
                    @else
                        —
                    @endif
                </div>
            </div>
            <div class="form-g mb-2">
                <div class="form-lbl">Assegnato a</div>
                <div class="cal-detail-value">{{ $calendarEvent->assignee?->name ?? 'Nessuno' }}</div>
            </div>
            <div class="form-g">
                <div class="form-lbl">Creato da</div>
                <div class="cal-detail-value">{{ $calendarEvent->creator?->name ?? 'Sistema' }}</div>
            </div>
        </x-panel>
    </div>


    {{-- Allegati --}}
    <div class="u-mt-lg u-mb-lg">
        <x-panel title="Allegati" dot="var(--accent)" padded>
            @if(count($calendarEvent->attachments ?? []))
                <table class="t-table u-mb-md">
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
                        @foreach($calendarEvent->attachments as $att)
                        <tr>
                            <td class="name-col">{{ $att->original_name }}</td>
                            <td class="mono-col">{{ $att->mime_type }}</td>
                            <td class="mono-col">{{ number_format($att->size / 1024, 0) }} KB</td>
                            <td>{{ $att->uploader?->name }}</td>
                            <td class="mono-col">{{ $att->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <div class="u-flex u-gap-sm">
                                    <a href="{{ route('attachments.download', $att) }}" target="_blank" class="btn-icon">↓</a>
                                    @can('delete', $att)
                                        <form action="{{ route('attachments.destroy', $att) }}" method="POST" onsubmit="return confirm('Eliminare il file?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn-icon u-text-red">×</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="u-empty-state-sm u-mb-md">Nessun allegato presente.</div>
            @endif

            @can('create', App\Models\Attachment::class)
            <div class="u-section-sep">
                <form action="{{ route('attachments.store') }}" method="POST" enctype="multipart/form-data" class="cal-upload-row">
                    @csrf
                    <input type="hidden" name="attachable_type" value="calendar_event">
                    <input type="hidden" name="attachable_id" value="{{ $calendarEvent->id }}">
                    <div class="u-flex-1">
                        <div class="form-lbl">Nuovo Allegato</div>
                        <input type="file" name="file" class="form-in cal-file-input" required>
                    </div>
                    <button type="submit" class="btn btn-p btn-sm">Carica →</button>
                </form>
                @error('file')
                    <div class="cal-err-sm">{{ $message }}</div>
                @enderror
            </div>
            @endcan
        </x-panel>
    </div>
</x-app-layout>