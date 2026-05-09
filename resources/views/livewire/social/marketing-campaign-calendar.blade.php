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

    <div class="g-1col">
        <div class="calendar-filters">
            <select wire:model.live="clientFilter" class="form-in calendar-select calendar-select-md">
                <option value="">Tutti i Clienti</option>
                @foreach($clients as $client)
                    <option value="{{ $client->id }}">{{ $client->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="campaignFilter" class="form-in calendar-select calendar-select-lg">
                <option value="">Tutte le Campagne</option>
                @foreach($campaigns as $campaign)
                    <option value="{{ $campaign->id }}">{{ $campaign->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="platformFilter" class="form-in calendar-select calendar-select-md">
                <option value="">Tutte le Piattaforme</option>
                @foreach($platforms as $platform)
                    <option value="{{ $platform->value }}">{{ $platform->label() }}</option>
                @endforeach
            </select>
            @if($clientFilter || $campaignFilter || $platformFilter)
                <button wire:click="$set('clientFilter', ''); $set('campaignFilter', ''); $set('platformFilter', '')" class="btn btn-g calendar-btn">Reset</button>
            @endif
        </div>

        <x-panel>
            <div class="panel-body pad">
                <div id="js-error" class="js-error-box"></div>
                <div wire:ignore>
                    <div id="calendar" style="min-height: 600px;"></div>
                </div>
            </div>
        </x-panel>
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
                    let dotColor = arg.event.backgroundColor || 'var(--accent)';
                    
                    let wrapper = document.createElement('div');
                    wrapper.style.display = 'flex';
                    wrapper.style.alignItems = 'flex-start';
                    wrapper.style.gap = '6px';
                    wrapper.style.padding = '2px 0';
                    
                    wrapper.innerHTML = `
                        <div style="width: 8px; height: 8px; border-radius: 50%; background-color: ${dotColor}; margin-top: 3px; flex-shrink: 0; box-shadow: 0 0 2px rgba(0,0,0,0.5);"></div>
                        <div style="display: flex; flex-direction: column; gap: 2px; overflow: hidden;">
                            <div style="font-size: 11px; font-weight: bold; line-height: 1.2; white-space: normal; word-break: break-word; color: ${dotColor};">
                                ${arg.timeText ? arg.timeText + ' ' : ''}${arg.event.title}
                            </div>
                            <div style="font-size: 10px; opacity: 0.8; white-space: normal; line-height: 1.1; color: var(--text3);">
                                ${arg.event.extendedProps.platform} - ${arg.event.extendedProps.campaign}
                            </div>
                        </div>
                    `;
                    return { domNodes: [ wrapper ] }
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
