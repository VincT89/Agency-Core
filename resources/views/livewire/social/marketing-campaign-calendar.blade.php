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

    <div class="mkt-calendar-filter-card u-mb-md">
        <div class="mkt-calendar-filters">
            <div class="u-flex-1">
                <select wire:model.live="clientFilter" class="form-sel">
                    <option value="">Tutti i Clienti</option>
                    @foreach($clients as $client)
                        <option value="{{ $client->id }}">{{ $client->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="u-flex-1">
                <select wire:model.live="campaignFilter" class="form-sel">
                    <option value="">Tutte le Campagne</option>
                    @foreach($campaigns as $campaign)
                        <option value="{{ $campaign->id }}">{{ $campaign->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="u-flex-1">
                <select wire:model.live="platformFilter" class="form-sel">
                    <option value="">Tutte le Piattaforme</option>
                    @foreach($platforms as $platform)
                        <option value="{{ $platform->value }}">{{ $platform->label() }}</option>
                    @endforeach
                </select>
            </div>
            @if($clientFilter || $campaignFilter || $platformFilter)
                <div>
                    <button wire:click="$set('clientFilter', ''); $set('campaignFilter', ''); $set('platformFilter', '')" class="btn btn-g">Reset</button>
                </div>
            @endif
        </div>
    </div>


    <div class="cal-page" id="view-calendar">
        <div class="cal-wrapper-modern">
            <div id="js-error" class="u-text-red u-mb-sm u-font-mono u-whitespace-pre-wrap"></div>
            <div wire:ignore>
                <div id="calendar" class="u-min-h-600"></div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/locales/it.global.min.js"></script>
<script>
    document.addEventListener('livewire:initialized', function() {
        try {
            const jsErr = document.getElementById('js-error');
            if (typeof FullCalendar === 'undefined') {
                jsErr.innerText = "ERRORE: FullCalendar non è stato caricato.";
                return;
            }

            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'it',
                firstDay: 1,
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                buttonText: {
                    today: 'Oggi',
                    month: 'Mese',
                    week: 'Settimana',
                    day: 'Giorno',
                    list: 'Lista'
                },
                themeSystem: 'standard',
                height: 'auto',
                events: function(fetchInfo, successCallback, failureCallback) {
                    @this.fetchEvents().then(events => {
                        successCallback(events);
                    }).catch(err => {
                        console.error("Errore caricamento eventi:", err);
                        failureCallback(err);
                    });
                },
                eventClick: function(info) {
                    info.jsEvent.preventDefault();
                    if (info.event.url) {
                        window.location.href = info.event.url;
                    }
                },

                eventContent: function(arg) {
                    let wrapper = document.createElement('div');
                    wrapper.classList.add('cal-mkt-event');
                    
                    let titleEl = document.createElement('div');
                    titleEl.classList.add('cal-mkt-event-title');
                    titleEl.textContent = (arg.timeText ? arg.timeText + ' ' : '') + arg.event.title;
                    
                    let subEl = document.createElement('div');
                    subEl.classList.add('cal-mkt-event-sub');
                    subEl.textContent = arg.event.extendedProps.platform + ' - ' + arg.event.extendedProps.campaign;
                    
                    wrapper.appendChild(titleEl);
                    wrapper.appendChild(subEl);
                    
                    return { domNodes: [ wrapper ] };
                }
            });

            calendar.render();

            // Ricarica eventi se i filtri cambiano
            Livewire.hook('commit', ({ succeed }) => {
                succeed(() => {
                    calendar.refetchEvents();
                });
            });
        } catch(err) {
            document.getElementById('js-error').innerText = "JS Exception: " + err.message + "\n" + err.stack;
        }
    });
</script>


@endpush
