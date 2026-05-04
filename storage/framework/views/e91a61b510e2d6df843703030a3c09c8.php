<div>
    <?php if (isset($component)) { $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['eyebrow' => 'Social Media','meta' => 'Pianificazione e pubblicazione']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['eyebrow' => 'Social Media','meta' => 'Pianificazione e pubblicazione']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

         <?php $__env->slot('title', null, []); ?> <strong>Calendario Editoriale</strong> <?php $__env->endSlot(); ?>
         <?php $__env->slot('actions', null, []); ?> 
            <a href="<?php echo e(route('social.posts.index')); ?>" class="btn btn-g">Archivio Post</a>
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

    <div class="g-1col">
        <div class="calendar-filters">
            <select wire:model.live="clientFilter" class="form-in calendar-select calendar-select-md">
                <option value="">Tutti i Clienti</option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $clients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $client): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <option value="<?php echo e($client->id); ?>"><?php echo e($client->name); ?></option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </select>
            <select wire:model.live="projectFilter" class="form-in calendar-select calendar-select-lg">
                <option value="">Tutti i Progetti</option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $projects; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $project): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <option value="<?php echo e($project->id); ?>"><?php echo e($project->name); ?></option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </select>
            <select wire:model.live="platformFilter" class="form-in calendar-select calendar-select-md">
                <option value="">Tutte le Piattaforme</option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $platforms; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $platform): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <option value="<?php echo e($platform->value); ?>"><?php echo e($platform->label()); ?></option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </select>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($clientFilter || $projectFilter || $platformFilter): ?>
                <button wire:click="$set('clientFilter', ''); $set('projectFilter', ''); $set('platformFilter', '')" class="btn btn-g calendar-btn">Reset</button>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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

            <div class="panel-body pad">
                <div id="js-error" class="js-error-box"></div>
                <div wire:ignore>
                    <div id="calendar" style="min-height: 600px;"></div>
                </div>
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
</div>

<?php $__env->startPush('scripts'); ?>
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
                    window.Livewire.find('<?php echo e($_instance->getId()); ?>').fetchEvents().then(events => {
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
                                ${arg.event.extendedProps.platform} - ${arg.event.extendedProps.project}
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
        padding: 1px 4px !important; cursor: pointer; background: transparent !important; border: none !important; box-shadow: none !important; }
    .fc-toolbar-title { font-family: var(--serif); font-style: italic;
        font-size: 20px !important; color: var(--text); }
    .fc-list-event:hover td { background: var(--bg2) !important; }
    .fc-list-event-title a { color: var(--text) !important; text-decoration: none; }

    /* Custom UI overrides per i filtri */
    .calendar-filters { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; align-items: center; justify-content: flex-end; }
    .calendar-select { padding: 6px 12px; font-size: 12px; width: auto !important; height: auto !important; display: inline-block; }
    .calendar-select-md { min-width: 180px; max-width: 250px; }
    .calendar-select-lg { min-width: 220px; max-width: 300px; }
    .calendar-btn { padding: 5px 10px; font-size: 11px; }

    .filter-lbl { font-size: 11px; font-weight: 600; color: var(--text3); text-transform: uppercase; margin-bottom: 4px; display: block;}
    .project-sel, .platform-sel { background: var(--bg2); color: var(--text); border: 1px solid var(--line2); border-radius: var(--r); padding: 4px 8px; font-size: 12px; }
    .js-error-box:empty { display: none; }
    .js-error-box { color:var(--red); margin-bottom:10px; font-family:monospace; white-space:pre-wrap; }
</style>
<?php $__env->stopPush(); ?>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\GESTIONALE\Agency-core\resources\views/livewire/social/editorial-calendar.blade.php ENDPATH**/ ?>