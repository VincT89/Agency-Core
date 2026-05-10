<x-app-layout title="Calendario">
    <x-page-header
        eyebrow="Modulo · Operativo"
        
    >
    <x-slot:title><strong>Calendario</strong> Eventi</x-slot:title>
        <div class="u-text-sm u-text-muted u-mt-xs">Pianificazione di incontri, appuntamenti cliente e milestone. Per il progresso operativo usa i <a href="{{ route('tasks.index') }}" class="u-text-accent u-no-underline">Task</a>.</div>
        <x-slot:actions>
            @can('create', App\Models\CalendarEvent::class)
                <a href="{{ route('calendar-events.create') }}" class="btn btn-p">+ Nuovo evento</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if(auth()->user()->role === \App\Enums\UserRole::Admin)
    <div class="cal-toolbar-wrapper u-mb-lg">
        <div class="cal-toolbar">
            <span class="cal-toolbar-label"><i data-lucide="filter" class="u-icon-sm"></i> Filtra Reparto:</span>
            <div class="cal-toolbar-pills">
                @php $currentDept = request('department'); @endphp
                <a href="{{ request()->fullUrlWithQuery(['department' => null]) }}" class="cal-pill {{ !$currentDept ? 'is-active' : '' }}">Tutti</a>
                <a href="{{ request()->fullUrlWithQuery(['department' => 'developer']) }}" class="cal-pill {{ $currentDept==='developer' ? 'is-active' : '' }}">Developer</a>
                <a href="{{ request()->fullUrlWithQuery(['department' => 'marketing']) }}" class="cal-pill {{ $currentDept==='marketing' ? 'is-active' : '' }}">Marketing</a>
                <a href="{{ request()->fullUrlWithQuery(['department' => 'photographer']) }}" class="cal-pill {{ $currentDept==='photographer' ? 'is-active' : '' }}">Fotografo</a>
                <a href="{{ request()->fullUrlWithQuery(['department' => 'graphic_designer']) }}" class="cal-pill {{ $currentDept==='graphic_designer' ? 'is-active' : '' }}">Grafica</a>
                <a href="{{ request()->fullUrlWithQuery(['department' => 'administration']) }}" class="cal-pill {{ $currentDept==='administration' ? 'is-active' : '' }}">Amministrazione</a>
            </div>
        </div>
    </div>
    @endif

    <div class="cal-intro-box u-mb-md">
        <i data-lucide="info" class="cal-intro-icon"></i>
        <span><strong>Suggerimento:</strong> Clicca su un giorno vuoto nel calendario per pianificare rapidamente un nuovo evento.</span>
    </div>

    <div class="cal-page" id="view-calendar">
        <div class="cal-wrapper-modern">
            <div id="js-error" class="u-text-red u-mb-sm u-font-mono u-whitespace-pre-wrap"></div>
            <div id="fullcalendar" class="u-min-h-600"></div>
        </div>
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
                            titleEl.classList.add('has-video-call');
                        } else {
                            // Per list-view il titolo è in fc-list-event-title
                            let listTitleEl = info.el.querySelector('.fc-list-event-title a');
                            if (listTitleEl) {
                                listTitleEl.classList.add('has-video-call', 'has-video-call-accent');
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