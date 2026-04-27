<x-app-layout title="{{ $ticket->title }}">
    <x-page-header
        eyebrow="Ticket #{{ $ticket->id }}"
        
    >
    <x-slot:title><strong>{{ $ticket->title }}</strong></x-slot:title>
        <x-slot:actions>
            <x-badge :status="$ticket->status" :label="$ticket->status_label" />
            @can('update', $ticket)
                <a href="{{ route('tickets.edit', $ticket) }}" class="btn btn-g">Modifica</a>
            @endcan
        
            @can('delete', $ticket)
                <form action="{{ route('tickets.destroy', $ticket) }}" method="POST"
                      onsubmit="return confirm('Eliminare il ticket #{{ $ticket->id }}?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-g" style="color:var(--red);border-color:rgba(245,75,75,.3)">
                        Elimina
                    </button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="g-2col-main">
        <div>
            <x-panel title="Dettagli Ticket" padded>
                <div style="display:flex;gap:12px;margin-bottom:20px">
                    <x-badge :status="$ticket->type" :label="$ticket->type_label" />
                    <x-badge :status="$ticket->priority" :label="$ticket->priority_label" />
                </div>
                
                @if($ticket->description)
                <div style="color:var(--text);font-size:14px;line-height:1.6;white-space:pre-wrap">{{ $ticket->description }}</div>
                @else
                <div style="color:var(--text3);font-style:italic">Nessuna descrizione fornita.</div>
                @endif

                @if($ticket->resolution_notes)
                <div style="margin-top:24px;padding:16px;background:var(--bg2);border-radius:6px;border:1px solid var(--green);border-left:4px solid var(--green);">
                    <div style="font-weight:600;font-size:13px;color:var(--green);margin-bottom:8px;display:flex;align-items:center;gap:6px;">
                        <i data-lucide="check-circle" style="width:16px;height:16px;"></i> Note di Risoluzione
                    </div>
                    <div style="color:var(--text);font-size:13px;line-height:1.5;">{{ $ticket->resolution_notes }}</div>
                </div>
                @endif
            </x-panel>
        </div>

        <div>
            <x-panel title="Info Base" dot="var(--yellow)" padded>
                <div class="form-g mb-2">
                    <div class="form-lbl">Codice Ticket</div>
                    <div style="color:var(--text);font-family:var(--mono)">{{ $ticket->code ?? '—' }}</div>
                </div>
                <div class="form-g mb-2">
                    <div class="form-lbl">Cliente</div>
                    <div style="color:var(--text);font-family:var(--sans)">
                        @if($ticket->client)
                            <a href="{{ route('clients.show', $ticket->client) }}" style="color:var(--accent);text-decoration:none">{{ $ticket->client->name }}</a>
                        @else
                            —
                        @endif
                    </div>
                </div>
                <div class="form-g mb-2">
                    <div class="form-lbl">Progetto</div>
                    <div style="color:var(--text);font-family:var(--sans)">
                        @if($ticket->project)
                            <a href="{{ route('projects.show', $ticket->project) }}" style="color:var(--accent);text-decoration:none">{{ $ticket->project->name }}</a>
                        @else
                            —
                        @endif
                    </div>
                </div>
                <div class="form-g mb-2">
                    <div class="form-lbl">Assegnato a</div>
                    <div style="color:var(--text);font-family:var(--sans)">{{ $ticket->assignee?->name ?? 'Non assegnato' }}</div>
                </div>
                <div class="form-g mb-2">
                    <div class="form-lbl">Creato da</div>
                    <div style="color:var(--text);font-family:var(--sans)">{{ $ticket->creator?->name ?? 'Sistema' }}</div>
                </div>
                <div class="form-g mb-2">
                    <div class="form-lbl">Aperto il</div>
                    <div style="color:var(--text);font-family:var(--mono)">{{ $ticket->opened_at?->isoFormat('D MMMM YYYY') ?? $ticket->created_at->isoFormat('D MMMM YYYY') }}</div>
                </div>
                @if($ticket->due_date)
                <div class="form-g mb-2">
                    <div class="form-lbl">Scadenza</div>
                    <div style="color:var(--text);font-family:var(--mono)">{{ $ticket->due_date->isoFormat('D MMMM YYYY') }}</div>
                </div>
                @endif
            </x-panel>
        </div>
    </div>

    <x-audit-timeline :logs="$ticket->auditLogs" />

    {{-- Allegati --}}
    <x-attachments-panel :model="$ticket" />
</x-app-layout>