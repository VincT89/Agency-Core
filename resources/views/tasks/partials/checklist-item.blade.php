<div class="u-flex-center u-gap-sm task-checklist-item task-checklist-entry" data-checklist-item="{{ $item->id }}" role="group" aria-labelledby="task-checklist-title-{{ $item->id }}">
    <div class="u-flex-1 task-checklist-content">
        <div id="task-checklist-title-{{ $item->id }}" class="task-checklist-title {{ $item->is_completed ? 'u-text-muted task-checklist-completed' : 'u-text-strong' }}" data-checklist-title>
            {{ $item->title }}
        </div>
        <div class="u-text-meta task-checklist-meta task-checklist-details">
            <span id="task-checklist-status-{{ $item->id }}" class="task-checklist-status" data-checklist-status aria-live="polite">{{ $item->is_completed ? 'Completata' : 'Da fare' }}</span>
            <span data-checklist-completed-by-wrapper class="{{ $item->is_completed ? '' : 'task-checklist-hidden' }}">
                completato da <span data-checklist-completed-by>{{ $item->completedBy?->name ?? '—' }}</span>
            </span>
        </div>
    </div>

    @can('update', $task ?? $item->task)
        <form action="{{ route('task-checklist-items.toggle', $item) }}" method="POST" class="js-checklist-toggle task-checklist-toggle">
            @csrf
            @method('PATCH')
            <button type="submit" class="btn btn-g btn-sm" data-checklist-toggle-button aria-label="{{ $item->is_completed ? 'Riapri' : 'Segna completata' }}" aria-describedby="task-checklist-status-{{ $item->id }}">
                <span class="task-checklist-mobile-label" data-checklist-action>{{ $item->is_completed ? 'Riapri' : 'Segna completata' }}</span>
                <span class="task-checklist-desktop-symbol" data-checklist-symbol aria-hidden="true">{{ $item->is_completed ? '✓' : '○' }}</span>
            </button>
        </form>
        <div class="task-checklist-delete">
            <x-delete-modal
                action="{{ route('task-checklist-items.destroy', $item) }}"
                title="Elimina Voce Checklist"
                message="Sei sicuro di voler eliminare questa voce?"
                formClass="js-checklist-destroy"
                formIdAttr="{{ $item->id }}">
                <button type="button" class="btn-icon u-text-red task-checklist-delete-button" aria-label="Elimina voce">
                    <span class="task-checklist-mobile-label">Elimina voce</span>
                    <span class="task-checklist-desktop-symbol" aria-hidden="true">✕</span>
                </button>
            </x-delete-modal>
        </div>
    @endcan
    <p class="task-checklist-error" data-checklist-error role="alert" hidden></p>
</div>
