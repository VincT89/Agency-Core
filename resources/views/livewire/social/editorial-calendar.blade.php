<div>
    <x-page-header
        eyebrow="Social Media"
        meta="Pianificazione e pubblicazione"
    >
        <x-slot:title><strong>Calendario Editoriale</strong></x-slot:title>
        <x-slot:actions>
            <a href="{{ route('social.posts.index') }}" class="btn btn-g">Archivio Post</a>
        </x-slot:actions>
    </x-page-header>

    <div class="g-1col">
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;align-items:center;justify-content:flex-end">
            <select wire:model.live="projectFilter" class="form-in" style="padding:5px 10px;font-size:11px;width:200px">
                <option value="">Tutti i Progetti</option>
                @foreach($projects as $project)
                    <option value="{{ $project->id }}">{{ $project->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="platformFilter" class="form-in" style="padding:5px 10px;font-size:11px;width:160px">
                <option value="">Tutte le Piattaforme</option>
                @foreach($platforms as $platform)
                    <option value="{{ $platform->value }}">{{ $platform->label() }}</option>
                @endforeach
            </select>
            @if($projectFilter || $platformFilter)
                <button wire:click="$set('projectFilter', ''); $set('platformFilter', '')" class="btn btn-g" style="padding:5px 10px;font-size:11px">Reset</button>
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
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
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
                    let italicEl = document.createElement('div');
                    italicEl.innerHTML = `
                        <div style="font-size: 10px; font-weight: bold; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            ${arg.timeText} ${arg.event.title}
                        </div>
                        <div style="font-size: 9px; opacity: 0.8;">
                            ${arg.event.extendedProps.platform} - ${arg.event.extendedProps.project}
                        </div>
                    `;
                    return { domNodes: [ italicEl ] }
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

    /* Custom UI overrides per i filtri */
    .filter-lbl { font-size: 11px; font-weight: 600; color: var(--text3); text-transform: uppercase; margin-bottom: 4px; display: block;}
    .project-sel, .platform-sel { background: var(--bg2); color: var(--text); border: 1px solid var(--line2); border-radius: var(--r); padding: 4px 8px; font-size: 12px; }
    .project-sel { min-width: 200px; }
    .platform-sel { min-width: 150px; }
    .js-error-box:empty { display: none; }
    .js-error-box { color:var(--red); margin-bottom:10px; font-family:monospace; white-space:pre-wrap; }
</style>
@endpush
