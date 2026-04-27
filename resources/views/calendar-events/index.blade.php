<x-app-layout title="Calendario">
    <x-page-header
        eyebrow="Modulo · Operativo"
        
    >
    <x-slot:title><strong>Calendario</strong> Eventi</x-slot:title>
        <div style="font-size:14px;color:var(--text3);margin-top:8px">Pianificazione di incontri, appuntamenti cliente e milestone. Per il progresso operativo usa i <a href="{{ route('tasks.index') }}" style="color:var(--accent);text-decoration:none">Task</a>.</div>
        <x-slot:actions>
            {{-- Toggle vista --}}
            <div style="display:flex;border:1px solid var(--line2);border-radius:var(--r);overflow:hidden">
                <button id="btn-cal" onclick="switchView('calendar')"
                        class="btn" style="border:none;border-radius:0;padding:7px 14px;font-size:11px;background:var(--accent);color:#fff">
                    Calendario
                </button>
                <button id="btn-list" onclick="switchView('list')"
                        class="btn" style="border:none;border-radius:0;padding:7px 14px;font-size:11px;background:transparent;color:var(--text2)">
                    Lista
                </button>
            </div>
            @can('create', App\Models\CalendarEvent::class)
                <a href="{{ route('calendar-events.create') }}" class="btn btn-p">+ Nuovo evento</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if(auth()->user()->role === \App\Enums\UserRole::Admin)
    <div class="pills" style="margin-bottom: 16px;">
        @php $currentDept = request('department'); @endphp
        <span style="font-size: 11px; color: var(--text3); margin-right: 8px; font-weight: 600; text-transform: uppercase;">Reparto:</span>
        <a href="{{ request()->fullUrlWithQuery(['department' => null]) }}" class="pill {{ !$currentDept ? 'on' : '' }}">Tutti</a>
        <a href="{{ request()->fullUrlWithQuery(['department' => 'developer']) }}" class="pill {{ $currentDept==='developer' ? 'on' : '' }}">Developer</a>
        <a href="{{ request()->fullUrlWithQuery(['department' => 'marketing']) }}" class="pill {{ $currentDept==='marketing' ? 'on' : '' }}">Marketing</a>
        <a href="{{ request()->fullUrlWithQuery(['department' => 'photographer']) }}" class="pill {{ $currentDept==='photographer' ? 'on' : '' }}">Fotografo</a>
        <a href="{{ request()->fullUrlWithQuery(['department' => 'graphic_designer']) }}" class="pill {{ $currentDept==='graphic_designer' ? 'on' : '' }}">Grafica</a>
        <a href="{{ request()->fullUrlWithQuery(['department' => 'administration']) }}" class="pill {{ $currentDept==='administration' ? 'on' : '' }}">Amministrazione</a>
    </div>
    @endif

    {{-- Vista Calendario --}}
    <div id="view-calendar">
        <x-panel>
            <div class="panel-body pad">
                <div id="js-error" style="color:var(--red);margin-bottom:10px;font-family:monospace;white-space:pre-wrap"></div>
                <div id="fullcalendar" style="min-height:600px"></div>
            </div>
        </x-panel>
    </div>

    {{-- Vista Lista --}}
    <div id="view-list" style="display:none">
        <div class="pills">
            @php $currentStatus = request('status'); @endphp
            <a href="{{ route('calendar-events.index') }}" class="pill {{ !$currentStatus ? 'on' : '' }}">Tutti</a>
            <a href="{{ route('calendar-events.index', ['status'=>'scheduled']) }}"
               class="pill {{ $currentStatus==='scheduled' ? 'on' : '' }}">Programmati</a>
            <a href="{{ route('calendar-events.index', ['status'=>'completed']) }}"
               class="pill {{ $currentStatus==='completed' ? 'on' : '' }}">Completati</a>
            <a href="{{ route('calendar-events.index', ['status'=>'cancelled']) }}"
               class="pill {{ $currentStatus==='cancelled' ? 'on' : '' }}">Annullati</a>
        </div>
        <x-panel>
            <table class="t-table">
                <thead>
                    <tr>
                        <th>Data Inizio</th>
                        <th>Titolo Evento</th>
                        <th>Tipo</th>
                        <th>Assegnato a</th>
                        <th>Cliente</th>
                        <th>Stato</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($calendarEvents as $event)
                    <tr onclick="window.location='{{ route('calendar-events.show', $event) }}'" style="cursor:pointer">
                        <td class="mono-col">
                            {{ $event->start_at?->format('d/m/Y H:i') }}
                            @if($event->is_all_day)
                                <span style="font-size:10px;background:var(--line);padding:2px 4px;border-radius:4px;margin-left:4px">Tutto il giorno</span>
                            @endif
                        </td>
                        <td class="name-col">
                            {{ $event->title }}
                            @if($event->meeting_url)
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left:6px; color:var(--accent); vertical-align:middle"><path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5"/><rect x="2" y="6" width="14" height="12" rx="2"/></svg>
                            @endif
                        </td>
                        <td><x-badge :status="$event->type" :label="$event->type_label" /></td>
                        <td>{{ $event->assignee?->name ?? '—' }}</td>
                        <td>{{ $event->client?->name ?? '—' }}</td>
                        <td><x-badge :status="$event->status" :label="$event->status_label" /></td>
                        <td>
                            @can('update', $event)
                                <a href="{{ route('calendar-events.edit', $event) }}" class="btn-icon" onclick="event.stopPropagation()">✎</a>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" style="text-align:center;color:var(--text3);padding:32px">Nessun evento trovato</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $calendarEvents->links() }}
        </x-panel>
    </div>

    @push('scripts')
    {{-- FullCalendar via CDN --}}
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/locales/it.global.min.js"></script>

    <script>
    const CREATE_URL = '{{ route('calendar-events.create') }}';
    const EVENTS_URL = '{{ route('calendar-events.index') }}';
    const CURRENT_DEPT = '{{ request('department') }}';
    let calendarInstance = null;

    // Switch vista calendario/lista
    function switchView(v) {
        const isCalendar = v === 'calendar';
        document.getElementById('view-calendar').style.display = isCalendar ? '' : 'none';
        document.getElementById('view-list').style.display     = isCalendar ? 'none' : '';
        document.getElementById('btn-cal').style.background    = isCalendar ? 'var(--accent)' : 'transparent';
        document.getElementById('btn-cal').style.color         = isCalendar ? '#fff' : 'var(--text2)';
        document.getElementById('btn-list').style.background   = isCalendar ? 'transparent' : 'var(--accent)';
        document.getElementById('btn-list').style.color        = isCalendar ? 'var(--text2)' : '#fff';
        localStorage.setItem('calView', v);
        
        if (isCalendar && calendarInstance) {
            setTimeout(() => calendarInstance.updateSize(), 50);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        try {
            const jsErr = document.getElementById('js-error');
            if (typeof FullCalendar === 'undefined') {
                jsErr.innerText = "ERRORE CRITICO: FullCalendar undefined.";
                return;
            }

            // Ripristina vista preferita
            const savedView = localStorage.getItem('calView') || 'calendar';
            if (savedView === 'list') switchView('list');

            // Inizializza FullCalendar
            const calEl = document.getElementById('fullcalendar');
            calendarInstance = new FullCalendar.Calendar(calEl, {
                locale: 'it',
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left:   'prev,next today',
                    center: 'title',
                    right:  'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                },
                height: 'auto',
                nowIndicator: true,
                selectable: true,
                eventDisplay: 'block',
                dayMaxEvents: 3,

                // Fetch eventi dal server
                events: {
                    url: EVENTS_URL,
                    extraParams: { format: 'json', department: CURRENT_DEPT },
                    failure: function(err) {
                        console.error('Errore caricamento eventi calendario:', err);
                        alert("Impossibile scaricare gli eventi dal database.");
                    }
                },

                // Click su evento → apri show
                eventClick: function(info) {
                    info.jsEvent.preventDefault();
                    if (info.event.url) {
                        window.location.href = info.event.url;
                    }
                },

                // Aggiungi icona video se ha una call
                eventDidMount: function(info) {
                    if (info.event.extendedProps.has_call) {
                        let titleEl = info.el.querySelector('.fc-event-title');
                        if (titleEl) {
                            titleEl.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px; vertical-align:middle"><path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5"/><rect x="2" y="6" width="14" height="12" rx="2"/></svg>` + titleEl.innerHTML;
                        } else {
                            // Per list-view il titolo è in fc-list-event-title
                            let listTitleEl = info.el.querySelector('.fc-list-event-title a');
                            if (listTitleEl) {
                                listTitleEl.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px; color:var(--accent); vertical-align:middle"><path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5"/><rect x="2" y="6" width="14" height="12" rx="2"/></svg>` + listTitleEl.innerHTML;
                            }
                        }
                    }
                },

                // Click su giorno vuoto → crea evento con data pre-compilata
                dateClick: function(info) {
                    window.location.href = CREATE_URL
                        + '?start_at=' + encodeURIComponent(info.dateStr + 'T09:00');
                },

                // Tooltip al hover
                eventMouseEnter: function(info) {
                    const p = info.event.extendedProps;
                    info.el.title = [
                        info.event.title,
                        p.client  ? 'Cliente: ' + p.client   : null,
                        p.assignee? 'Assegnato: ' + p.assignee : null,
                        'Stato: ' + p.status,
                    ].filter(Boolean).join('\n');
                },

                // Stile scuro coerente col design system
                themeSystem: 'standard',
            });

            calendarInstance.render();

        } catch(err) {
            document.getElementById('js-error').innerText = "JS Exception: " + err.message + "\nStack:\n" + err.stack;
        }
    });
    </script>

    <style>
    /* Override FullCalendar per dark theme */
    .fc { --fc-border-color: var(--line); --fc-button-bg-color: var(--bg2);
          --fc-button-border-color: var(--line2); --fc-button-hover-bg-color: var(--bg3);
          --fc-button-hover-border-color: var(--line3); --fc-button-active-bg-color: var(--accent);
          --fc-button-active-border-color: var(--accent); --fc-button-text-color: var(--text2);
          --fc-today-bg-color: rgba(200,16,46,.06); --fc-page-bg-color: transparent;
          --fc-neutral-bg-color: var(--bg1); --fc-list-event-hover-bg-color: var(--bg2); }
    .fc-theme-standard td, .fc-theme-standard th { border-color: var(--line); }
    .fc-col-header-cell { background: var(--bg1); }
    .fc-col-header-cell-cushion { font-family: var(--mono); font-size: 10px;
        letter-spacing: .08em; text-transform: uppercase; color: var(--text3); text-decoration: none; }
    .fc-daygrid-day-number { font-family: var(--mono); font-size: 11px; color: var(--text2); }
    .fc-daygrid-day.fc-day-today .fc-daygrid-day-number { color: var(--accent); font-weight: 700; }
    .fc-button { font-family: var(--sans) !important; font-size: 11px !important;
        font-weight: 600 !important; letter-spacing: .03em !important;
        border-radius: var(--r) !important; padding: 5px 12px !important; }
    .fc-button-primary:not(:disabled).fc-button-active,
    .fc-button-primary:not(:disabled):active { background-color: var(--accent) !important;
        border-color: var(--accent) !important; color: #fff !important; }
    .fc-event { font-family: var(--sans); font-size: 11px; border-radius: 3px !important;
        padding: 1px 4px !important; cursor: pointer; }
    .fc-toolbar-title { font-family: var(--serif); font-style: italic;
        font-size: 20px !important; color: var(--text); }
    .fc-list-event:hover td { background: var(--bg2) !important; }
    .fc-list-event-title a { color: var(--text) !important; text-decoration: none; }
    </style>
    @endpush
</x-app-layout>