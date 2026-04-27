<x-app-layout title="{{ $calendarEvent->title }}">
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
                    <button type="submit" class="btn btn-g" style="color:var(--red);border-color:rgba(245,75,75,.3)">
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
                <div style="color:var(--text);font-family:var(--mono);font-size:15px;font-weight:500;">
                    {{ $calendarEvent->start_at?->isoFormat('D MMMM YYYY - HH:mm') }}
                    @if($calendarEvent->is_all_day)
                        <span style="font-size:10px;background:var(--line);padding:2px 4px;border-radius:4px;margin-left:8px;font-family:var(--sans)">Tutto il giorno</span>
                    @endif
                </div>
            </div>
            
            @if($calendarEvent->end_at)
            <div class="form-g mb-2">
                <div class="form-lbl">Fine</div>
                <div style="color:var(--text);font-family:var(--mono)">{{ $calendarEvent->end_at->isoFormat('D MMMM YYYY - HH:mm') }}</div>
            </div>
            @endif

            <div class="form-g mb-2">
                <div class="form-lbl">Tipo</div>
                <div><x-badge :status="$calendarEvent->type" :label="$calendarEvent->type_label" /></div>
            </div>

            @if($calendarEvent->location)
            <div class="form-g mb-2">
                <div class="form-lbl">Luogo Fisico</div>
                <div style="color:var(--text);font-family:var(--sans)">{{ $calendarEvent->location }}</div>
            </div>
            @endif

            @if($calendarEvent->meeting_url)
            <div class="form-g">
                <div class="form-lbl">Videochiamata ({{ $calendarEvent->meeting_provider === 'nextcloud_talk' ? 'Nextcloud Talk' : 'Altro' }})</div>
                <div style="margin-top: 6px;">
                    <a href="{{ $calendarEvent->meeting_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-p" style="display:inline-flex; align-items:center; gap:6px; padding: 6px 12px; font-size: 12px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-video"><path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5"/><rect x="2" y="6" width="14" height="12" rx="2"/></svg>
                        Accedi alla call
                    </a>
                </div>
            </div>
            @endif

            @if($calendarEvent->description)
            <div class="form-g" style="margin-top:16px;">
                <div class="form-lbl">Descrizione</div>
                <div style="color:var(--text3);font-size:13px;white-space:pre-wrap">{{ $calendarEvent->description }}</div>
            </div>
            @endif
        </x-panel>

        <x-panel title="Collegamento & Team" dot="var(--teal)" padded>
            <div class="form-g mb-2">
                <div class="form-lbl">Cliente</div>
                <div style="color:var(--text);font-family:var(--sans)">
                    @if($calendarEvent->client)
                        <a href="{{ route('clients.show', $calendarEvent->client) }}" style="color:var(--accent);text-decoration:none">{{ $calendarEvent->client->name }}</a>
                    @else
                        —
                    @endif
                </div>
            </div>
            <div class="form-g mb-2">
                <div class="form-lbl">Progetto</div>
                <div style="color:var(--text);font-family:var(--sans)">
                    @if($calendarEvent->project)
                        <a href="{{ route('projects.show', $calendarEvent->project) }}" style="color:var(--accent);text-decoration:none">{{ $calendarEvent->project->name }}</a>
                    @else
                        —
                    @endif
                </div>
            </div>
            <div class="form-g mb-2">
                <div class="form-lbl">Assegnato a</div>
                <div style="color:var(--text);font-family:var(--sans)">{{ $calendarEvent->assignee?->name ?? 'Nessuno' }}</div>
            </div>
            <div class="form-g">
                <div class="form-lbl">Creato da</div>
                <div style="color:var(--text);font-family:var(--sans)">{{ $calendarEvent->creator?->name ?? 'Sistema' }}</div>
            </div>
        </x-panel>
    </div>


    {{-- Allegati --}}
    <div style="margin-top:20px;margin-bottom:20px;">
        <x-panel title="Allegati" dot="var(--accent)" padded>
            @if(count($calendarEvent->attachments ?? []))
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
                        @foreach($calendarEvent->attachments as $att)
                        <tr>
                            <td class="name-col">{{ $att->original_name }}</td>
                            <td class="mono-col">{{ $att->mime_type }}</td>
                            <td class="mono-col">{{ number_format($att->size / 1024, 0) }} KB</td>
                            <td>{{ $att->uploader?->name }}</td>
                            <td class="mono-col">{{ $att->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <div style="display:flex;gap:8px">
                                    <a href="{{ route('attachments.download', $att) }}" target="_blank" class="btn-icon">↓</a>
                                    @can('delete', $att)
                                        <form action="{{ route('attachments.destroy', $att) }}" method="POST" onsubmit="return confirm('Eliminare il file?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn-icon" style="color:var(--red)">×</button>
                                        </form>
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

            @can('create', App\Models\Attachment::class)
            <div style="border-top:1px solid var(--line);padding-top:16px;">
                <form action="{{ route('attachments.store') }}" method="POST" enctype="multipart/form-data" style="display:flex;gap:16px;align-items:flex-end">
                    @csrf
                    <input type="hidden" name="attachable_type" value="calendar_event">
                    <input type="hidden" name="attachable_id" value="{{ $calendarEvent->id }}">
                    <div style="flex:1">
                        <div class="form-lbl">Nuovo Allegato</div>
                        <input type="file" name="file" class="form-in" required style="padding:4px 8px;font-size:12px;height:auto">
                    </div>
                    <button type="submit" class="btn btn-p" style="padding:6px 12px">Carica →</button>
                </form>
                @error('file')
                    <div style="color:var(--red);font-size:12px;margin-top:4px">{{ $message }}</div>
                @enderror
            </div>
            @endcan
        </x-panel>
    </div>
</x-app-layout>