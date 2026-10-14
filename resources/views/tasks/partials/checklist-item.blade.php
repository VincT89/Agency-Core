<div class="task-checklist-entry" data-checklist-item="{{ $item->id }}" role="group" aria-labelledby="task-checklist-title-{{ $item->id }}">
    <div class="task-checklist-content">
        <div id="task-checklist-title-{{ $item->id }}" class="task-checklist-title {{ $item->is_completed ? 'u-text-muted task-checklist-completed' : 'u-text-strong' }}" data-checklist-title>
            {{ $item->title }}
        </div>
        <div class="task-checklist-details">
            <span id="task-checklist-status-{{ $item->id }}" class="task-checklist-status" data-checklist-status aria-live="polite">{{ $item->is_completed ? 'Completata' : 'Da fare' }}</span>
            <span data-checklist-completed-by-wrapper class="{{ $item->is_completed ? '' : 'task-checklist-hidden' }}">
                Completata da <span data-checklist-completed-by>{{ $item->completedBy?->name ?? '—' }}</span>
            </span>
        </div>
    </div>

    @can('update', $task ?? $item->task)
        <form action="{{ route('task-checklist-items.toggle', $item) }}" method="POST" class="js-checklist-toggle task-checklist-toggle">
            @csrf
            @method('PATCH')
            <button type="submit" class="btn btn-g btn-sm" data-checklist-toggle-button aria-describedby="task-checklist-status-{{ $item->id }}">
                {{ $item->is_completed ? 'Riapri' : 'Segna completata' }}
            </button>
        </form>
        <div class="task-checklist-delete">
            <x-delete-modal
                action="{{ route('task-checklist-items.destroy', $item) }}"
                title="Elimina Voce Checklist"
                message="Sei sicuro di voler eliminare questa voce?"
                formClass="js-checklist-destroy"
                formIdAttr="{{ $item->id }}">
                <button type="button" class="btn-ghost-danger btn-xs">Elimina voce</button>
            </x-delete-modal>
        </div>
    @endcan
    <p class="task-checklist-error" data-checklist-error role="alert" hidden></p>
</div>
