<x-app-layout title="Modifica Team">
    <x-page-header eyebrow="Modulo · Operativo" >
    <x-slot:title><strong>Modifica</strong> team</x-slot:title>
        <x-slot:actions>
            <a href="{{ route('teams.show', $team) }}" class="btn btn-g">← Indietro</a>
        </x-slot:actions>
    </x-page-header>

    <x-panel padded>
        <form action="{{ route('teams.update', $team) }}" method="POST">
            @csrf
            @method('PATCH')

            <div class="form-row">
                <x-form-group label="Nome team" name="name" required>
                    <input name="name" class="form-in @error('name') is-invalid @enderror"
                           value="{{ old('name', $team->name) }}" placeholder="Es. Team Frontend">
                </x-form-group>
                <x-form-group label="Stato" name="is_active">
                    <select name="is_active" class="form-sel">
                        <option value="1" {{ old('is_active', $team->is_active) == '1' ? 'selected' : '' }}>Attivo</option>
                        <option value="0" {{ old('is_active', $team->is_active) == '0' ? 'selected' : '' }}>Inattivo</option>
                    </select>
                </x-form-group>
            </div>

            <div class="form-row full">
                <x-form-group label="Descrizione" name="description">
                    <textarea name="description" class="form-ta @error('description') is-invalid @enderror"
                              rows="3">{{ old('description', $team->description) }}</textarea>
                </x-form-group>
            </div>

            <div class="sec-lbl" style="margin-top:16px">Membri</div>
            <div style="border:1px solid var(--line);border-radius:var(--r);overflow:hidden;margin-bottom:16px">
                @foreach($users as $user)
                @php
                    $isMember = in_array($user->id, old('members', $team->users->pluck('id')->toArray()));
                    $userRole = old("roles.{$user->id}", $team->users->find($user->id)?->pivot->role ?? 'member');
                @endphp
                <label style="display:flex;align-items:center;gap:12px;padding:10px 14px;
                               border-bottom:1px solid var(--line);cursor:pointer;transition:background .12s"
                       class="hover-bg">
                    <input type="checkbox" name="members[]" value="{{ $user->id }}"
                           id="member_{{ $user->id }}"
                           {{ $isMember ? 'checked' : '' }}
                           style="accent-color:var(--accent)">
                    <div style="flex:1">
                        <div style="font-size:12px;font-weight:600;color:var(--text)">{{ $user->name }}</div>
                        <div style="font-family:var(--mono);font-size:9px;color:var(--text3)">
                            {{ $user->role->value }}
                            @if($user->primary_specialization) · {{ $user->primary_specialization }}@endif
                        </div>
                    </div>
                    <select name="roles[{{ $user->id }}]"
                            class="form-sel" style="width:120px;font-size:11px;padding:4px 8px">
                        <option value="member"  {{ $userRole === 'member'  ? 'selected' : '' }}>Membro</option>
                        <option value="lead"    {{ $userRole === 'lead'    ? 'selected' : '' }}>Lead</option>
                        <option value="support" {{ $userRole === 'support' ? 'selected' : '' }}>Support</option>
                    </select>
                </label>
                @endforeach
            </div>

            <div class="modal-ft" style="border-top:1px solid var(--line);padding-top:16px">
                <a href="{{ route('teams.show', $team) }}" class="btn btn-g">Annulla</a>
                <button type="submit" class="btn btn-p">Aggiorna Team</button>
            </div>
        </form>
    </x-panel>
</x-app-layout>
