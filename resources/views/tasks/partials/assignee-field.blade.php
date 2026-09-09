<x-form-group label="Assegnato a" name="assigned_to">
    <select name="assigned_to" class="form-sel @error('assigned_to') is-invalid @enderror"
        data-task-assignee aria-describedby="task-assignee-help">
        <option value="">Non assegnato</option>
        @foreach($users as $user)
            @php
                $eligible = $user->status === 'active' && $user->can('viewAny', \App\Models\Task::class);
                $available = $eligible && $selectedProjectId
                    && ($user->canBypassProjectScope() || $user->projects->contains('id', $selectedProjectId));
                $selected = (string) $selectedAssignee === (string) $user->id;
            @endphp
            <option value="{{ $user->id }}" data-eligible="{{ $eligible ? '1' : '0' }}"
                data-projects="{{ implode(',', $user->projects->modelKeys()) }}"
                data-global="{{ $user->canBypassProjectScope() ? '1' : '0' }}"
                @selected($selected) @disabled(!$available) @if(!$available && !$selected) hidden @endif>{{ $user->name }}</option>
        @endforeach
    </select>
    <p id="task-assignee-help" class="u-text-meta u-text-sm" role="status" data-task-assignee-help>Puoi scegliere chi ha accesso alle task di questo progetto. Se manca un collega, aggiungilo al team di commessa.</p>
</x-form-group>
