<x-app-layout title="Calendario">
    <x-page-header
        eyebrow="Modulo · Operativo"
        
    >
    <x-slot:title><strong>Calendario</strong> Eventi</x-slot:title>
        <div style="font-size:14px;color:var(--text3);margin-top:8px">Pianificazione di incontri, appuntamenti cliente e milestone. Per il progresso operativo usa i <a href="{{ route('tasks.index') }}" style="color:var(--accent);text-decoration:none">Task</a>.</div>
        <x-slot:actions>
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

    <div id="view-calendar">
        <x-panel>
            <div class="panel-body pad">
                <div id="js-error" style="color:var(--red);margin-bottom:10px;font-family:monospace;white-space:pre-wrap"></div>
                <div id="fullcalendar" style="min-height:600px"></div>
            </div>
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

    document.addEventListener('DOMContentLoaded', function() {
        try {
            const jsErr = document.getElementById('js-error');
            if (typeof FullCalendar === 'undefined') {
                jsErr.innerText = "ERRORE CRITICO: FullCalendar undefined.";
                return;
            }

            // Inizializza FullCalendar
            const calEl = document.getElementById('fullcalendar');
            calendarInstance = new FullCalendar.Calendar(calEl, {
                locale: 'it',
                firstDay: 1, // Start on Monday
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left:   'prev,next today',
                    center: 'title',
                    right:  'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                },
                buttonText: {
                    today: 'Oggi',
                    month: 'Mese',
                    week: 'Settimana',
                    day: 'Giorno',
                    list: 'Lista'
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

    
    @endpush
</x-app-layout>