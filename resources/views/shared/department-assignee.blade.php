@php
    $selectedUser = $users->firstWhere('id', $selectedAssignee);
    $selectedDepartment = old('assignment_department', $selectedUser?->role->value ?? ($suggestedDepartment ?? ''));
    $departments = $users->pluck('role')->unique(fn ($role) => $role->value)->sortBy(fn ($role) => $role->label());
@endphp
<div class="form-row" data-department-assignee data-assignment-context="{{ $assignmentContext }}">
    <x-form-group label="Reparto del referente" name="assignment_department">
        <select name="assignment_department" class="form-sel" data-assignment-department>
            <option value="">Seleziona reparto</option>
            @foreach($departments as $department)
                <option value="{{ $department->value }}" @selected($selectedDepartment === $department->value)>{{ \App\Models\Ticket::DEPARTMENTS[$department->value] ?? $department->label() }}</option>
            @endforeach
        </select>
    </x-form-group>
    <x-form-group label="Referente" name="assigned_to">
        <select name="assigned_to" class="form-sel" data-assignment-user aria-describedby="assignment-help">
            <option value="">Non assegnato</option>
            @foreach($users as $user)
                <option value="{{ $user->id }}" data-department="{{ $user->role->value }}"
                    data-projects="{{ implode(',', $user->projects->modelKeys()) }}"
                    data-global="{{ $user->canBypassProjectScope() ? '1' : '0' }}"
                    @selected((string) $selectedAssignee === (string) $user->id)>{{ $user->name }}</option>
            @endforeach
        </select>
        <p id="assignment-help" class="u-text-meta" role="status"></p>
    </x-form-group>
</div>
