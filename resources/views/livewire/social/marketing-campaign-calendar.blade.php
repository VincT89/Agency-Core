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
                    <span class="cal-mini-title">{{ ucfirst($currentDate->copy()->locale('it')->translatedFormat('F Y')) }}</span>
                    <div class="cal-mini-nav">
                        <button type="button" wire:click="goToPreviousCalendarMonth" class="btn-cal-nav u-cursor-pointer" aria-label="Mese precedente"><i
                                data-lucide="chevron-left" class="u-icon-sm" aria-hidden="true"></i></button>
                        <button type="button" wire:click="goToNextCalendarMonth" class="btn-cal-nav u-cursor-pointer" aria-label="Mese successivo"><i
                                data-lucide="chevron-right" class="u-icon-sm" aria-hidden="true"></i></button>
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
                        <button type="button" wire:click="setCalendarDate('{{ $day->toDateString() }}')"
                            data-date="{{ $day->toDateString() }}"
                            aria-label="{{ ucfirst($day->copy()->locale('it')->translatedFormat('l j F Y')) }}"
                            @if($isToday) aria-current="date" @endif
                            aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                            class="cal-mini-day u-cursor-pointer {{ $isCurrentMonth ? '' : 'is-other-month' }} {{ $isSelected ? 'is-selected' : '' }} {{ $isToday ? 'is-today' : '' }} {{ $hasPublication ? 'has-publication' : '' }}">
                            {{ $day->day }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="cal-sidebar-filters">
                <span class="cal-sidebar-label">Filtra Calendario</span>
                <div class="u-mb-xs">
                    <label for="marketing-calendar-client-filter" class="sr-only">Filtra per cliente</label>
                    <select id="marketing-calendar-client-filter" wire:model.live="clientFilter" class="form-sel">
                        <option value="">Tutti i Clienti</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}">{{ $client->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="u-mb-xs">
                    <label for="marketing-calendar-campaign-filter" class="sr-only">Filtra per campagna</label>
                    <select id="marketing-calendar-campaign-filter" wire:model.live="campaignFilter" class="form-sel">
                        <option value="">Tutte le Campagne</option>
                        @foreach($campaigns as $campaign)
                            <option value="{{ $campaign->id }}">{{ $campaign->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="u-mb-xs">
                    <label for="marketing-calendar-platform-filter" class="sr-only">Filtra per piattaforma</label>
                    <select id="marketing-calendar-platform-filter" wire:model.live="platformFilter" class="form-sel">
                        <option value="">Tutte le Piattaforme</option>
                        @foreach($platforms as $platform)
                            <option value="{{ $platform->value }}">{{ $platform->label() }}</option>
                        @endforeach
                    </select>
                </div>
                @if($clientFilter || $campaignFilter || $platformFilter)
                    <div class="u-mt-sm">
                        <button type="button" wire:click="$set('clientFilter', ''); $set('campaignFilter', ''); $set('platformFilter', '')" class="btn btn-g u-w-full">Azzera filtri</button>
                    </div>
                @endif
            </div>
        </aside>

        <main class="cal-gmain">
            <div class="cal-wrapper-modern">
                <div id="js-error" class="u-alert-error u-mb-sm" role="alert" hidden></div>
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
        if (jsErr) {
            jsErr.textContent = '';
            jsErr.hidden = true;
        }

        if (typeof FullCalendar === 'undefined') {
            if (jsErr) {
                jsErr.innerText = 'Il calendario non è disponibile in questo momento. Ricarica la pagina.';
                jsErr.hidden = false;
            }
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
            buttonHints: {
                prev: 'Periodo precedente',
                next: 'Periodo successivo',
                today: 'Vai a oggi',
                dayGridMonth: 'Visualizza il mese',
                timeGridWeek: 'Visualizza la settimana',
                timeGridDay: 'Visualizza il giorno'
            },
            viewHint: 'Visualizza $0',
            themeSystem: 'standard',
            height: window.innerWidth < 901 ? 650 : '100%',
            expandRows: true,
            slotDuration: '01:00:00',
            slotMinTime: '08:00:00',
            slotMaxTime: '20:00:00',
            allDaySlot: false,
            defaultTimedEventDuration: '01:00:00',
            dayHeaderContent: arg => new Intl.DateTimeFormat('it-IT', {
                weekday: 'short',
                day: '2-digit'
            }).format(arg.date).replace('.', '').toUpperCase(),
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
        const refreshCalendarSize = () => window.marketingGlobalCalendar?.updateSize();
        requestAnimationFrame(refreshCalendarSize);
        setTimeout(refreshCalendarSize, 350);

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
            console.error('Errore inizializzazione calendario marketing:', err);
            const jsErr = document.getElementById('js-error');
            if (jsErr) {
                jsErr.innerText = 'Il calendario non è disponibile in questo momento. Ricarica la pagina.';
                jsErr.hidden = false;
            }
        }
    });
</script>


@endpush
