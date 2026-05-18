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
        
        // Giorni per la griglia del mini-mese
        $startOfCalendarMonth = $startOfMonth->copy()->startOfWeek();
        $endOfCalendarMonth = $endOfMonth->copy()->endOfWeek();
        $monthDays = [];
        $dateCursor = $startOfCalendarMonth->copy();
        while ($dateCursor <= $endOfCalendarMonth) {
            $monthDays[] = $dateCursor->copy();
            $dateCursor->addDay();
        }

        // Giorni per la Kanban (solo settimana corrente)
        $startOfWeek = $currentDate->copy()->startOfWeek();
        $endOfWeek = $currentDate->copy()->endOfWeek();
        $weekDays = [];
        $dateCursor = $startOfWeek->copy();
        while ($dateCursor <= $endOfWeek) {
            $weekDays[] = $dateCursor->copy();
            $dateCursor->addDay();
        }

        $prevMonth = $currentDate->copy()->subMonth()->toDateString();
        $nextMonth = $currentDate->copy()->addMonth()->toDateString();
        $prevWeek = $currentDate->copy()->subWeek()->toDateString();
        $nextWeek = $currentDate->copy()->addWeek()->toDateString();
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
                        <a href="{{ request()->fullUrlWithQuery(['date' => $prevMonth]) }}" wire:navigate class="btn-cal-nav"><i
                                data-lucide="chevron-left" class="u-icon-sm"></i></a>
                        <a href="{{ request()->fullUrlWithQuery(['date' => $nextMonth]) }}" wire:navigate class="btn-cal-nav"><i
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
                    @foreach($monthDays as $day)
                        @php
                            $isCurrentMonth = $day->month === $currentDate->month;
                            $isToday = $day->isToday();
                            $isSelected = $day->toDateString() === $currentDate->toDateString();
                        @endphp
                        <a href="{{ request()->fullUrlWithQuery(['date' => $day->toDateString()]) }}" wire:navigate
                            data-date="{{ $day->toDateString() }}"
                            class="cal-mini-day {{ $isCurrentMonth ? '' : 'is-other-month' }} {{ $isSelected ? 'is-selected' : '' }} {{ $isToday ? 'is-today' : '' }}">
                            {{ $day->day }}
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="cal-sidebar-filters">
                <span class="cal-sidebar-label">Filtra Vista</span>
                @php $currentDept = request('department'); @endphp
                <a href="{{ request()->fullUrlWithQuery(['department' => null, 'scope' => null]) }}"
                    class="cal-sidebar-filter {{ !$currentDept && !request('scope') ? 'is-active' : '' }}">Tutti gli Eventi</a>
                <a href="{{ request()->fullUrlWithQuery(['scope' => 'personal', 'department' => null]) }}"
                    class="cal-sidebar-filter {{ request('scope') === 'personal' ? 'is-active' : '' }}">I miei Personali</a>
                
                @if(auth()->user()->role === \App\Enums\UserRole::Admin)
                    <div class="u-mt-sm u-mb-xs" style="height: 1px; background: var(--border);"></div>
                    <a href="{{ request()->fullUrlWithQuery(['department' => 'developer', 'scope' => null]) }}"
                        class="cal-sidebar-filter {{ $currentDept === 'developer' ? 'is-active' : '' }}">Developer</a>
                    <a href="{{ request()->fullUrlWithQuery(['department' => 'marketing', 'scope' => null]) }}"
                        class="cal-sidebar-filter {{ $currentDept === 'marketing' ? 'is-active' : '' }}">Marketing</a>
                    <a href="{{ request()->fullUrlWithQuery(['department' => 'photographer', 'scope' => null]) }}"
                        class="cal-sidebar-filter {{ $currentDept === 'photographer' ? 'is-active' : '' }}">Fotografo</a>
                    <a href="{{ request()->fullUrlWithQuery(['department' => 'graphic_designer', 'scope' => null]) }}"
                        class="cal-sidebar-filter {{ $currentDept === 'graphic_designer' ? 'is-active' : '' }}">Grafica</a>
                    <a href="{{ request()->fullUrlWithQuery(['department' => 'administration', 'scope' => null]) }}"
                        class="cal-sidebar-filter {{ $currentDept === 'administration' ? 'is-active' : '' }}">Amministrazione</a>
                @endif
            </div>
        </aside>

        <main class="cal-gmain" x-data="calendarKanbanApp('{{ $startOfWeek->toDateString() }}', '{{ $endOfWeek->toDateString() }}')" @view-mode-changed.window="viewMode = $event.detail">
            <div class="u-flex-between u-mb-md u-px-md">
                <div x-show="viewMode === 'kanban'" class="u-flex u-items-center u-gap-md" style="display: none;">
                    <h3 class="u-text-lg u-font-medium">Vista Kanban ({{ $startOfWeek->format('d/m') }} - {{ $endOfWeek->format('d/m') }})</h3>
                    <div class="cal-mini-nav" style="display: inline-flex;">
                        <a href="{{ request()->fullUrlWithQuery(['date' => $prevWeek]) }}" wire:navigate class="btn-cal-nav"><i data-lucide="chevron-left" class="u-icon-sm"></i></a>
                        <a href="{{ request()->fullUrlWithQuery(['date' => $nextWeek]) }}" wire:navigate class="btn-cal-nav"><i data-lucide="chevron-right" class="u-icon-sm"></i></a>
                    </div>
                </div>
                <h3 class="u-text-lg u-font-medium" x-show="viewMode === 'calendar'">Vista Calendario</h3>
                
                <div class="tab-switcher u-flex-center u-inline-flex">
                    <button type="button" @click="$dispatch('view-mode-changed', 'calendar')" class="tab-btn" :class="{'active': viewMode === 'calendar'}">Calendario</button>
                    <button type="button" @click="$dispatch('view-mode-changed', 'kanban')" class="tab-btn" :class="{'active': viewMode === 'kanban'}">Kanban</button>
                </div>
            </div>

            <div class="cal-wrapper-modern" x-show="viewMode === 'calendar'">
                <div id="js-error" class="u-text-red u-mb-sm u-font-mono u-whitespace-pre-wrap"></div>
                <div id="fullcalendar" class="cal-full-height"></div>
            </div>

            <div class="kanban" x-show="viewMode === 'kanban'" style="display: none; height: calc(100vh - 180px); overflow-x: auto; padding: 0 16px;">
                <div class="u-flex u-gap-md u-h-full">
                    @foreach($weekDays as $index => $day)
                        @if($day->dayOfWeek !== 0) {{-- Exclude Sunday --}}
                            <div class="k-col u-flex-shrink-0" style="width: 300px; display: flex; flex-direction: column; max-height: 100%;">
                                <div class="k-col-title u-mb-sm">
                                    <span>{{ ucfirst($day->translatedFormat('l d/m')) }}</span>
                                    <span class="badge badge-subtle" x-text="events['{{ $day->toDateString() }}'] ? events['{{ $day->toDateString() }}'].length : 0"></span>
                                </div>
                                <div class="k-cards u-flex-1 u-overflow-y-auto sortable-col" data-date="{{ $day->toDateString() }}" style="min-height: 100px;">
                                    <template x-for="evt in events['{{ $day->toDateString() }}']" :key="evt.id">
                                        <div class="k-card enhanced js-clickable-row u-cursor-pointer" :data-id="evt.id" @click="window.Livewire.navigate(evt.url)" :style="'border-left: 4px solid ' + evt.backgroundColor">
                                            <div class="k-card-title task-title" x-text="evt.title"></div>
                                            <div class="k-card-meta u-mb-xs" x-text="evt.extendedProps.client || 'Nessun cliente'"></div>
                                            <div class="u-flex-between">
                                                <span class="k-card-meta" x-text="evt.extendedProps.assignee || 'Non assegnato'"></span>
                                                <span class="k-card-meta u-text-main" x-text="formatTime(evt.start) + (evt.end ? ' - ' + formatTime(evt.end) : '')"></span>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </main>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
        {{-- FullCalendar via CDN --}}
        <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/locales/it.global.min.js"></script>

        <script>
            const CREATE_URL = '{{ route('calendar-events.create') }}';
            const EVENTS_URL = '{{ route('calendar-events.index') }}';
            const CURRENT_DEPT = '{{ request('department') }}';
            const CURRENT_SCOPE = '{{ request('scope') }}';

            function cleanupCalendarEvents() {
                if (window.calendarEventsInstance) {
                    window.calendarEventsInstance.destroy();
                    window.calendarEventsInstance = null;
                }
            }

            function initCalendarEvents() {
                cleanupCalendarEvents();
                try {
                    const jsErr = document.getElementById('js-error');
                    if (typeof FullCalendar === 'undefined') {
                        if (jsErr) jsErr.innerText = "ERRORE CRITICO: FullCalendar undefined.";
                        return;
                    }

                    const calEl = document.getElementById('fullcalendar');
                    if (!calEl) return;
                    
                    window.calendarEventsInstance = new FullCalendar.Calendar(calEl, {
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
                        slotMaxTime: '24:00:00',
                        slotLabelFormat: {
                            hour: '2-digit',
                            minute: '2-digit',
                            omitZeroMinute: false,
                            meridiem: false
                        },
                        slotLabelInterval: '00:30:00',
                        allDaySlot: true,
                        dayHeaderFormat: { weekday: 'short', day: '2-digit', omitCommas: true },
                        nowIndicator: true,
                        selectable: true,
                        selectMirror: true,
                        eventDisplay: 'block',
                        dayMaxEvents: 3,

                        // Fetch eventi dal server
                        events: {
                            url: EVENTS_URL,
                            extraParams: { format: 'json', department: CURRENT_DEPT, scope: CURRENT_SCOPE },
                            failure: function (err) {
                                console.error('Errore caricamento eventi calendario:', err);
                                alert("Impossibile scaricare gli eventi dal database.");
                            }
                        },

                        // Click su evento → apri show
                        eventClick: function (info) {
                            info.jsEvent.preventDefault();
                            if (info.event.url) {
                                window.Livewire.navigate(info.event.url);
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
                            window.Livewire.navigate(CREATE_URL
                                + '?start_at=' + encodeURIComponent(info.startStr)
                                + '&end_at=' + encodeURIComponent(info.endStr));
                        },

                        // Click rapido singolo su uno slot
                        dateClick: function(info) {
                            // Nelle viste con orario, info.dateStr ha già data+ora. Nella vista mese ha solo la data.
                            let targetDate = info.dateStr;
                            if (targetDate.length <= 10) {
                                targetDate += 'T09:00'; // Fallback per vista mese
                            }
                            window.Livewire.navigate(CREATE_URL
                                + '?start_at=' + encodeURIComponent(targetDate));
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

                    window.calendarEventsInstance.render();

                    // La sincronizzazione tramite JS (gotoDate) è stata rimossa per permettere a wire:navigate
                    // di aggiornare correttamente sia la vista FullCalendar che la vista Kanban (che è renderizzata via Blade).

                } catch (err) {
                    const jsErr = document.getElementById('js-error');
                    if (jsErr) jsErr.innerText = "JS Exception: " + err.message + "\nStack:\n" + err.stack;
                }
            }

            document.addEventListener('livewire:navigating', cleanupCalendarEvents);

            document.addEventListener('livewire:navigated', function () {
                initCalendarEvents();
            });

            document.addEventListener('alpine:init', () => {
                Alpine.data('calendarKanbanApp', (startDate, endDate) => ({
                    viewMode: localStorage.getItem('calendarViewMode') || 'calendar',
                    events: {},
                    rawEvents: [],
                    
                    init() {
                        this.fetchEvents();
                        this.$watch('viewMode', value => {
                            localStorage.setItem('calendarViewMode', value);
                            if (value === 'kanban' && this.rawEvents.length === 0) {
                                this.fetchEvents();
                            } else if (value === 'kanban') {
                                this.initSortable();
                            }
                        });
                    },

                    async fetchEvents() {
                        try {
                            const params = new URLSearchParams({
                                format: 'json',
                                start: startDate,
                                end: endDate,
                                department: CURRENT_DEPT,
                                scope: CURRENT_SCOPE
                            });
                            
                            const response = await fetch(`${EVENTS_URL}?${params}`);
                            this.rawEvents = await response.json();
                            this.groupEvents();
                            
                            if (this.viewMode === 'kanban') {
                                this.$nextTick(() => {
                                    this.initSortable();
                                });
                            }
                        } catch (e) {
                            console.error("Error fetching kanban events", e);
                        }
                    },

                    groupEvents() {
                        const grouped = {};
                        // Inizializza array per tutti i giorni
                        document.querySelectorAll('.sortable-col').forEach(col => {
                            grouped[col.dataset.date] = [];
                        });
                        
                        this.rawEvents.forEach(evt => {
                            if (!evt.start) return;
                            const datePart = evt.start.split('T')[0];
                            if (grouped[datePart]) {
                                grouped[datePart].push(evt);
                            } else {
                                grouped[datePart] = [evt];
                            }
                        });
                        
                        this.events = grouped;
                    },

                    formatTime(isoString) {
                        if (!isoString || isoString.length <= 10) return '';
                        const date = new Date(isoString);
                        return date.toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' });
                    },

                    initSortable() {
                        const columns = document.querySelectorAll('.sortable-col');
                        const self = this;
                        
                        columns.forEach(col => {
                            if (col._sortable) col._sortable.destroy();
                            
                            col._sortable = new Sortable(col, {
                                group: 'calendar-kanban',
                                animation: 150,
                                ghostClass: 'k-card-ghost',
                                onEnd: async function (evt) {
                                    const itemEl = evt.item;
                                    const eventId = itemEl.dataset.id;
                                    const newDate = evt.to.dataset.date;
                                    const oldDate = evt.from.dataset.date;
                                    
                                    if (newDate === oldDate) return;
                                    
                                    // Trova l'evento
                                    const eventObj = self.rawEvents.find(e => e.id == eventId);
                                    if (!eventObj) return;
                                    
                                    // Calcola nuovi start_at e end_at mantenendo gli orari
                                    const oldStart = new Date(eventObj.start);
                                    const newStart = new Date(newDate);
                                    newStart.setHours(oldStart.getHours(), oldStart.getMinutes(), oldStart.getSeconds());
                                    
                                    let newEndStr = null;
                                    if (eventObj.end) {
                                        const oldEnd = new Date(eventObj.end);
                                        const duration = oldEnd.getTime() - oldStart.getTime();
                                        const newEnd = new Date(newStart.getTime() + duration);
                                        // Aggiusta per timezone locale in ISO
                                        newEndStr = new Date(newEnd.getTime() - (newEnd.getTimezoneOffset() * 60000)).toISOString().slice(0, 19).replace('T', ' ');
                                    }
                                    
                                    const newStartStr = new Date(newStart.getTime() - (newStart.getTimezoneOffset() * 60000)).toISOString().slice(0, 19).replace('T', ' ');

                                    try {
                                        const res = await fetch(`/calendar-events/${eventId}/date`, {
                                            method: 'PATCH',
                                            headers: {
                                                'Content-Type': 'application/json',
                                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                                'Accept': 'application/json'
                                            },
                                            body: JSON.stringify({
                                                start_at: newStartStr,
                                                end_at: newEndStr
                                            })
                                        });
                                        
                                        if (res.ok) {
                                            // Aggiorna lo state interno
                                            eventObj.start = newStart.toISOString();
                                            if (eventObj.end) eventObj.end = new Date(newStartStr).toISOString(); // approssimato
                                            self.groupEvents();
                                            // Se il calendario FullCalendar è inizializzato, ricarica
                                            if (window.calendarEventsInstance) {
                                                window.calendarEventsInstance.refetchEvents();
                                            }
                                        } else {
                                            throw new Error('Update failed');
                                        }
                                    } catch (e) {
                                        console.error('Failed to update event date', e);
                                        alert('Errore durante l\'aggiornamento della data.');
                                        // Rollback visivo
                                        evt.from.insertBefore(itemEl, evt.from.children[evt.oldIndex]);
                                    }
                                }
                            });
                        });
                    }
                }));
            });
        </script>


    @endpush
</x-app-layout>