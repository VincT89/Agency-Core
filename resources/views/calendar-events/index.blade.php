<x-app-layout title="Calendario">
    <x-page-header eyebrow="Modulo · Operativo">
        <x-slot:title><strong>Calendario</strong> Eventi</x-slot:title>
        <div class="u-text-sm u-text-muted u-mt-xs">Pianificazione di incontri, appuntamenti cliente e milestone. Per il
            progresso operativo usa i <a href="{{ route('tasks.index') }}"
                class="u-text-accent u-no-underline">Task</a>.</div>
    </x-page-header>

    @php
        $currentDateStr = request('date', now()->toDateString());
        try {
            $currentDate = \Carbon\Carbon::parse($currentDateStr);
        } catch (\Throwable $e) {
            $currentDate = now();
        }
        $startOfMonth = $currentDate->copy()->startOfMonth();
        $endOfMonth = $currentDate->copy()->endOfMonth();
        $startOfWeek = $startOfMonth->copy()->startOfWeek();
        $endOfWeek = $endOfMonth->copy()->endOfWeek();

        $days = [];
        $dateCursor = $startOfWeek->copy();
        while ($dateCursor <= $endOfWeek) {
            $days[] = $dateCursor->copy();
            $dateCursor->addDay();
        }
        $prevMonth = $currentDate->copy()->subMonth()->toDateString();
        $nextMonth = $currentDate->copy()->addMonth()->toDateString();
    @endphp

    <div class="cal-gshell">
        <aside class="cal-gsidebar">
            @can('create', App\Models\CalendarEvent::class)
                <a href="{{ route('calendar-events.create') }}" class="btn btn-p cal-create-button u-mb-lg">+ Nuovo
                    evento</a>
            @endcan

            <div class="cal-mini-month u-mb-lg">
                <div class="cal-mini-header">
                    <span class="cal-mini-title">{{ ucfirst($currentDate->translatedFormat('F Y')) }}</span>
                    <div class="cal-mini-nav">
                        <a href="{{ request()->fullUrlWithQuery(['date' => $prevMonth]) }}" class="btn-cal-nav"><i
                                data-lucide="chevron-left" class="u-icon-sm"></i></a>
                        <a href="{{ request()->fullUrlWithQuery(['date' => $nextMonth]) }}" class="btn-cal-nav"><i
                                data-lucide="chevron-right" class="u-icon-sm"></i></a>
                    </div>
                </div>
                <div class="cal-mini-grid">
                    <div class="cal-mini-day-name">L</div>
                    <div class="cal-mini-day-name">M</div>
                    <div class="cal-mini-day-name">M</div>
                    <div class="cal-mini-day-name">G</div>
                    <div class="cal-mini-day-name">V</div>
                    <div class="cal-mini-day-name">S</div>
                    <div class="cal-mini-day-name">D</div>
                    @foreach($days as $day)
                        @php
                            $isCurrentMonth = $day->month === $currentDate->month;
                            $isToday = $day->isToday();
                            $isSelected = $day->toDateString() === $currentDate->toDateString();
                        @endphp
                        <a href="{{ request()->fullUrlWithQuery(['date' => $day->toDateString()]) }}"
                            data-date="{{ $day->toDateString() }}"
                            class="cal-mini-day {{ $isCurrentMonth ? '' : 'is-other-month' }} {{ $isSelected ? 'is-selected' : '' }} {{ $isToday ? 'is-today' : '' }}">
                            {{ $day->day }}
                        </a>
                    @endforeach
                </div>
            </div>

            @if(auth()->user()->role === \App\Enums\UserRole::Admin)
                <div class="cal-sidebar-filters">
                    <span class="cal-sidebar-label">Filtra Reparto</span>
                    @php $currentDept = request('department'); @endphp
                    <a href="{{ request()->fullUrlWithQuery(['department' => null]) }}"
                        class="cal-sidebar-filter {{ !$currentDept ? 'is-active' : '' }}">Tutti</a>
                    <a href="{{ request()->fullUrlWithQuery(['department' => 'developer']) }}"
                        class="cal-sidebar-filter {{ $currentDept === 'developer' ? 'is-active' : '' }}">Developer</a>
                    <a href="{{ request()->fullUrlWithQuery(['department' => 'marketing']) }}"
                        class="cal-sidebar-filter {{ $currentDept === 'marketing' ? 'is-active' : '' }}">Marketing</a>
                    <a href="{{ request()->fullUrlWithQuery(['department' => 'photographer']) }}"
                        class="cal-sidebar-filter {{ $currentDept === 'photographer' ? 'is-active' : '' }}">Fotografo</a>
                    <a href="{{ request()->fullUrlWithQuery(['department' => 'graphic_designer']) }}"
                        class="cal-sidebar-filter {{ $currentDept === 'graphic_designer' ? 'is-active' : '' }}">Grafica</a>
                    <a href="{{ request()->fullUrlWithQuery(['department' => 'administration']) }}"
                        class="cal-sidebar-filter {{ $currentDept === 'administration' ? 'is-active' : '' }}">Amministrazione</a>
                </div>
            @endif
        </aside>

        <main class="cal-gmain">
            <div class="cal-wrapper-modern">
                <div id="js-error" class="u-text-red u-mb-sm u-font-mono u-whitespace-pre-wrap"></div>
                <div id="fullcalendar" class="cal-full-height"></div>
            </div>
        </main>
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

            document.addEventListener('DOMContentLoaded', function () {
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
                        initialView: 'timeGridWeek',
                        initialDate: '{{ $currentDateStr }}',
                        headerToolbar: {
                            left: 'today prev,next',
                            center: 'title',
                            right: 'timeGridWeek,timeGridDay'
                        },
                        buttonText: {
                            today: 'Oggi',
                            week: 'Settimana',
                            day: 'Giorno'
                        },
                        height: '100%', // Adatta il calendario al flex container per avere solo scroll interno
                        slotMinTime: '08:00:00', // Nasconde la notte fonda
                        slotMaxTime: '20:00:00',
                        slotLabelFormat: {
                            hour: '2-digit',
                            minute: '2-digit',
                            omitZeroMinute: false,
                            meridiem: false
                        },
                        slotLabelInterval: '00:30:00',
                        allDaySlot: false,
                        dayHeaderFormat: { weekday: 'short', day: '2-digit', omitCommas: true },
                        nowIndicator: true,
                        selectable: true,
                        selectMirror: true,
                        eventDisplay: 'block',
                        dayMaxEvents: 3,

                        // Fetch eventi dal server
                        events: {
                            url: EVENTS_URL,
                            extraParams: { format: 'json', department: CURRENT_DEPT },
                            failure: function (err) {
                                console.error('Errore caricamento eventi calendario:', err);
                                alert("Impossibile scaricare gli eventi dal database.");
                            }
                        },

                        // Click su evento → apri show
                        eventClick: function (info) {
                            info.jsEvent.preventDefault();
                            if (info.event.url) {
                                window.location.href = info.event.url;
                            }
                        },

                        // Aggiungi icona video se ha una call
                        eventDidMount: function (info) {
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

                        // Selezione tramite trascinamento (drag)
                        select: function(info) {
                            window.location.href = CREATE_URL
                                + '?start_at=' + encodeURIComponent(info.startStr)
                                + '&end_at=' + encodeURIComponent(info.endStr);
                        },

                        // Click rapido singolo su uno slot
                        dateClick: function(info) {
                            // Nelle viste con orario, info.dateStr ha già data+ora. Nella vista mese ha solo la data.
                            let targetDate = info.dateStr;
                            if (targetDate.length <= 10) {
                                targetDate += 'T09:00'; // Fallback per vista mese
                            }
                            window.location.href = CREATE_URL
                                + '?start_at=' + encodeURIComponent(targetDate);
                        },

                        // Tooltip al hover
                        eventMouseEnter: function (info) {
                            const p = info.event.extendedProps;
                            info.el.title = [
                                info.event.title,
                                p.client ? 'Cliente: ' + p.client : null,
                                p.assignee ? 'Assegnato: ' + p.assignee : null,
                                'Stato: ' + p.status,
                            ].filter(Boolean).join('\n');
                        },

                        // Stile scuro coerente col design system
                        themeSystem: 'standard',
                    });

                    calendarInstance.render();

            // Sincronizza Mini-Mese con FullCalendar (evita refresh pagina)
            document.querySelectorAll('.cal-mini-day').forEach(function(dayEl) {
                dayEl.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Aggiorna stato visivo mini-mese
                    document.querySelectorAll('.cal-mini-day.is-selected').forEach(function(el) {
                        el.classList.remove('is-selected');
                    });
                    this.classList.add('is-selected');
                    
                    // Sposta calendario principale
                    const selectedDate = this.getAttribute('data-date');
                    if (selectedDate) {
                        calendarInstance.gotoDate(selectedDate);
                    }
                    
                    // Aggiorna URL per preservare lo stato (senza ricaricare)
                    const newUrl = this.getAttribute('href');
                    window.history.pushState({path: newUrl}, '', newUrl);
                });
            });

        } catch (err) {
                    document.getElementById('js-error').innerText = "JS Exception: " + err.message + "\nStack:\n" + err.stack;
                }
            });
        </script>


    @endpush
</x-app-layout>