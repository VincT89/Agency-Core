<div>
    <x-page-header
        eyebrow="Social Media"
        meta="Pianificazione e pubblicazione"
    >
        <x-slot:title><strong>Calendario Editoriale</strong></x-slot:title>
        <x-slot:actions>
            <a href="{{ route('marketing-campaigns.index') }}" class="btn btn-g">Progetti Marketing</a>
        </x-slot:actions>
    </x-page-header>

    @php
        try {
            $currentDate = \Carbon\Carbon::parse($calendarDate);
        } catch(\Exception $e) {
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

    <div class="cal-gshell" id="mkt-calendar-wrapper">
        <aside class="cal-gsidebar">
            <div class="cal-mini-month u-mb-lg">
                <div class="cal-mini-header">
                    <span class="cal-mini-title">{{ ucfirst($currentDate->translatedFormat('F Y')) }}</span>
                    <div class="cal-mini-nav">
                        <a wire:click="goToPreviousCalendarMonth" class="btn-cal-nav u-cursor-pointer"><i
                                data-lucide="chevron-left" class="u-icon-sm"></i></a>
                        <a wire:click="goToNextCalendarMonth" class="btn-cal-nav u-cursor-pointer"><i
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
                            $hasPublication = in_array($day->toDateString(), $publishedDates, true);
                        @endphp
                        <a wire:click="setCalendarDate('{{ $day->toDateString() }}')"
                            data-date="{{ $day->toDateString() }}"
                            class="cal-mini-day u-cursor-pointer {{ $isCurrentMonth ? '' : 'is-other-month' }} {{ $isSelected ? 'is-selected' : '' }} {{ $isToday ? 'is-today' : '' }} {{ $hasPublication ? 'has-publication' : '' }}">
                            {{ $day->day }}
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="cal-sidebar-filters">
                <span class="cal-sidebar-label">Filtra Calendario</span>
                <div class="u-mb-xs">
                    <select wire:model.live="clientFilter" class="form-sel">
                        <option value="">Tutti i Clienti</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}">{{ $client->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="u-mb-xs">
                    <select wire:model.live="campaignFilter" class="form-sel">
                        <option value="">Tutte le Campagne</option>
                        @foreach($campaigns as $campaign)
                            <option value="{{ $campaign->id }}">{{ $campaign->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="u-mb-xs">
                    <select wire:model.live="platformFilter" class="form-sel">
                        <option value="">Tutte le Piattaforme</option>
                        @foreach($platforms as $platform)
                            <option value="{{ $platform->value }}">{{ $platform->label() }}</option>
                        @endforeach
                    </select>
                </div>
                @if($clientFilter || $campaignFilter || $platformFilter)
                    <div class="u-mt-sm">
                        <button wire:click="$set('clientFilter', ''); $set('campaignFilter', ''); $set('platformFilter', '')" class="btn btn-g u-w-full">Reset Filtri</button>
                    </div>
                @endif
            </div>
        </aside>

        <main class="cal-gmain">
            <div class="cal-wrapper-modern">
                <div id="js-error" class="u-text-red u-mb-sm u-font-mono u-whitespace-pre-wrap"></div>
                <div wire:ignore class="cal-full-height">
                    <div id="marketing-global-calendar" class="cal-full-height"></div>
                </div>
            </div>
        </main>
    </div>
</div>

@push('scripts')
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/locales/it.global.min.js"></script>
<script>
    function cleanupMarketingGlobalCalendar() {
        if (window.marketingGlobalCalendar) {
            window.marketingGlobalCalendar.destroy();
            window.marketingGlobalCalendar = null;
        }
        if (window.marketingGlobalUnsubscribers) {
            window.marketingGlobalUnsubscribers.forEach(unsub => {
                if (typeof unsub === 'function') unsub();
            });
        }
        window.marketingGlobalUnsubscribers = [];
    }

    function initMarketingGlobalCalendar(component) {
        cleanupMarketingGlobalCalendar();

        const jsErr = document.getElementById('js-error');
        if (typeof FullCalendar === 'undefined') {
            if(jsErr) jsErr.innerText = "ERRORE: FullCalendar non è stato caricato.";
            return;
        }

        var calendarEl = document.getElementById('marketing-global-calendar');
        if (!calendarEl) return;

        window.marketingGlobalCalendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'timeGridWeek',
            initialDate: '{{ $calendarDate }}',
            locale: 'it',
            firstDay: 1,
            headerToolbar: {
                left: 'today prev,next',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            buttonText: {
                today: 'Oggi',
                month: 'Mese',
                week: 'Settimana',
                day: 'Giorno'
            },
            themeSystem: 'standard',
            height: '100%',
            expandRows: true,
            slotDuration: '01:00:00',
            slotMinTime: '08:00:00',
            slotMaxTime: '20:00:00',
            allDaySlot: false,
            defaultTimedEventDuration: '01:00:00',
            dayHeaderFormat: { weekday: 'short', day: '2-digit', omitCommas: true },
            slotLabelFormat: {
                hour: '2-digit',
                minute: '2-digit',
                omitZeroMinute: false,
            },
            dayMaxEvents: 3,
            moreLinkClick: 'popover',
            eventDisplay: 'block',
            events: function(fetchInfo, successCallback, failureCallback) {
                component.fetchEvents().then(events => {
                    successCallback(events);
                }).catch(err => {
                    console.error("Errore caricamento eventi:", err);
                    failureCallback(err);
                });
            },
            eventClick: function(info) {
                info.jsEvent.preventDefault();
                if (info.event.url) {
                    window.Livewire.navigate(info.event.url);
                }
            },
            eventContent: function(arg) {
                let wrapper = document.createElement('div');
                wrapper.classList.add('cal-mkt-event');
                
                let titleEl = document.createElement('div');
                titleEl.classList.add('cal-mkt-event-title');
                titleEl.textContent = arg.event.title;
                
                let subEl = document.createElement('div');
                subEl.classList.add('cal-mkt-event-sub');
                subEl.textContent = arg.event.extendedProps.client;
                
                wrapper.appendChild(titleEl);
                wrapper.appendChild(subEl);
                
                return { domNodes: [ wrapper ] };
            }
        });

        window.marketingGlobalCalendar.render();

        window.marketingGlobalUnsubscribers.push(
            Livewire.on('marketing-global-calendar-filters-updated', () => {
                if (window.marketingGlobalCalendar) {
                    window.marketingGlobalCalendar.refetchEvents();
                }
            })
        );

        window.marketingGlobalUnsubscribers.push(
            Livewire.on('marketing-global-calendar-date-changed', (payload) => {
                if (!window.marketingGlobalCalendar) return;

                let date = Array.isArray(payload) ? payload[0].date : payload.date;

                const dayEl = document.querySelector(`.cal-mini-day[data-date="${date}"]`);

                if (dayEl && dayEl.classList.contains('has-publication')) {
                    window.marketingGlobalCalendar.changeView('timeGridDay', date);
                } else {
                    window.marketingGlobalCalendar.gotoDate(date);
                }
            })
        );
    }

    document.addEventListener('livewire:navigating', cleanupMarketingGlobalCalendar);

    document.addEventListener('livewire:navigated', function() {
        const calendarEl = document.getElementById('marketing-global-calendar');
        if (!calendarEl) return;
        try {
            initMarketingGlobalCalendar(@this);
        } catch(err) {
            document.getElementById('js-error').innerText = "JS Exception: " + err.message + "\n" + err.stack;
        }
    });
</script>


@endpush
