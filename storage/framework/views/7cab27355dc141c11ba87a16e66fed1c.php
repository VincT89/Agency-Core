<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve(['title' => 'Calendario'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\AppLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

    <?php if (isset($component)) { $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['eyebrow' => 'Modulo · Operativo']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['eyebrow' => 'Modulo · Operativo']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

     <?php $__env->slot('title', null, []); ?> <strong>Calendario</strong> Eventi <?php $__env->endSlot(); ?>
        <div style="font-size:14px;color:var(--text3);margin-top:8px">Pianificazione di incontri, appuntamenti cliente e milestone. Per il progresso operativo usa i <a href="<?php echo e(route('tasks.index')); ?>" style="color:var(--accent);text-decoration:none">Task</a>.</div>
         <?php $__env->slot('actions', null, []); ?> 
            
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
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('create', App\Models\CalendarEvent::class)): ?>
                <a href="<?php echo e(route('calendar-events.create')); ?>" class="btn btn-p">+ Nuovo evento</a>
            <?php endif; ?>
         <?php $__env->endSlot(); ?>
     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e)): ?>
<?php $attributes = $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e; ?>
<?php unset($__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e)): ?>
<?php $component = $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e; ?>
<?php unset($__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e); ?>
<?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()->role === \App\Enums\UserRole::Admin): ?>
    <div class="pills" style="margin-bottom: 16px;">
        <?php $currentDept = request('department'); ?>
        <span style="font-size: 11px; color: var(--text3); margin-right: 8px; font-weight: 600; text-transform: uppercase;">Reparto:</span>
        <a href="<?php echo e(request()->fullUrlWithQuery(['department' => null])); ?>" class="pill <?php echo e(!$currentDept ? 'on' : ''); ?>">Tutti</a>
        <a href="<?php echo e(request()->fullUrlWithQuery(['department' => 'developer'])); ?>" class="pill <?php echo e($currentDept==='developer' ? 'on' : ''); ?>">Developer</a>
        <a href="<?php echo e(request()->fullUrlWithQuery(['department' => 'marketing'])); ?>" class="pill <?php echo e($currentDept==='marketing' ? 'on' : ''); ?>">Marketing</a>
        <a href="<?php echo e(request()->fullUrlWithQuery(['department' => 'photographer'])); ?>" class="pill <?php echo e($currentDept==='photographer' ? 'on' : ''); ?>">Fotografo</a>
        <a href="<?php echo e(request()->fullUrlWithQuery(['department' => 'graphic_designer'])); ?>" class="pill <?php echo e($currentDept==='graphic_designer' ? 'on' : ''); ?>">Grafica</a>
        <a href="<?php echo e(request()->fullUrlWithQuery(['department' => 'administration'])); ?>" class="pill <?php echo e($currentDept==='administration' ? 'on' : ''); ?>">Amministrazione</a>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div id="view-calendar">
        <?php if (isset($component)) { $__componentOriginal36665f0dc0e45320e21db1e20a989acf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36665f0dc0e45320e21db1e20a989acf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

            <div class="panel-body pad">
                <div id="js-error" style="color:var(--red);margin-bottom:10px;font-family:monospace;white-space:pre-wrap"></div>
                <div id="fullcalendar" style="min-height:600px"></div>
            </div>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal36665f0dc0e45320e21db1e20a989acf)): ?>
<?php $attributes = $__attributesOriginal36665f0dc0e45320e21db1e20a989acf; ?>
<?php unset($__attributesOriginal36665f0dc0e45320e21db1e20a989acf); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal36665f0dc0e45320e21db1e20a989acf)): ?>
<?php $component = $__componentOriginal36665f0dc0e45320e21db1e20a989acf; ?>
<?php unset($__componentOriginal36665f0dc0e45320e21db1e20a989acf); ?>
<?php endif; ?>
    </div>

    
    <div id="view-list" style="display:none">
        <div class="pills">
            <?php $currentStatus = request('status'); ?>
            <a href="<?php echo e(route('calendar-events.index')); ?>" class="pill <?php echo e(!$currentStatus ? 'on' : ''); ?>">Tutti</a>
            <a href="<?php echo e(route('calendar-events.index', ['status'=>'scheduled'])); ?>"
               class="pill <?php echo e($currentStatus==='scheduled' ? 'on' : ''); ?>">Programmati</a>
            <a href="<?php echo e(route('calendar-events.index', ['status'=>'completed'])); ?>"
               class="pill <?php echo e($currentStatus==='completed' ? 'on' : ''); ?>">Completati</a>
            <a href="<?php echo e(route('calendar-events.index', ['status'=>'cancelled'])); ?>"
               class="pill <?php echo e($currentStatus==='cancelled' ? 'on' : ''); ?>">Annullati</a>
        </div>
        <?php if (isset($component)) { $__componentOriginal36665f0dc0e45320e21db1e20a989acf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36665f0dc0e45320e21db1e20a989acf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

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
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $calendarEvents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <tr onclick="window.location='<?php echo e(route('calendar-events.show', $event)); ?>'" style="cursor:pointer">
                        <td class="mono-col">
                            <?php echo e($event->start_at?->format('d/m/Y H:i')); ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->is_all_day): ?>
                                <span style="font-size:10px;background:var(--line);padding:2px 4px;border-radius:4px;margin-left:4px">Tutto il giorno</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                        <td class="name-col">
                            <?php echo e($event->title); ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->meeting_url): ?>
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left:6px; color:var(--accent); vertical-align:middle"><path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5"/><rect x="2" y="6" width="14" height="12" rx="2"/></svg>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                        <td><?php if (isset($component)) { $__componentOriginal2ddbc40e602c342e508ac696e52f8719 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2ddbc40e602c342e508ac696e52f8719 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.badge','data' => ['status' => $event->type,'label' => $event->type_label]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($event->type),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($event->type_label)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $attributes = $__attributesOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $component = $__componentOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__componentOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?></td>
                        <td><?php echo e($event->assignee?->name ?? '—'); ?></td>
                        <td><?php echo e($event->client?->name ?? '—'); ?></td>
                        <td><?php if (isset($component)) { $__componentOriginal2ddbc40e602c342e508ac696e52f8719 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2ddbc40e602c342e508ac696e52f8719 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.badge','data' => ['status' => $event->status,'label' => $event->status_label]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($event->status),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($event->status_label)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $attributes = $__attributesOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $component = $__componentOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__componentOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?></td>
                        <td>
                            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('update', $event)): ?>
                                <a href="<?php echo e(route('calendar-events.edit', $event)); ?>" class="btn-icon" onclick="event.stopPropagation()">✎</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <tr><td colspan="7" style="text-align:center;color:var(--text3);padding:32px">Nessun evento trovato</td></tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
            <?php echo e($calendarEvents->links()); ?>

         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal36665f0dc0e45320e21db1e20a989acf)): ?>
<?php $attributes = $__attributesOriginal36665f0dc0e45320e21db1e20a989acf; ?>
<?php unset($__attributesOriginal36665f0dc0e45320e21db1e20a989acf); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal36665f0dc0e45320e21db1e20a989acf)): ?>
<?php $component = $__componentOriginal36665f0dc0e45320e21db1e20a989acf; ?>
<?php unset($__componentOriginal36665f0dc0e45320e21db1e20a989acf); ?>
<?php endif; ?>
    </div>

    <?php $__env->startPush('scripts'); ?>
    
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/locales/it.global.min.js"></script>

    <script>
    const CREATE_URL = '<?php echo e(route('calendar-events.create')); ?>';
    const EVENTS_URL = '<?php echo e(route('calendar-events.index')); ?>';
    const CURRENT_DEPT = '<?php echo e(request('department')); ?>';
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
    <?php $__env->stopPush(); ?>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?><?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\GESTIONALE\Agency-core\resources\views\calendar-events\index.blade.php ENDPATH**/ ?>