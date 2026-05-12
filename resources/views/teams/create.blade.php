<x-app-layout title="Nuovo Team">
    <x-page-header eyebrow="Modulo · Operativo" >
    <x-slot:title><strong>Nuovo</strong> team</x-slot:title>
        <x-slot:actions>
            <a href="{{ route('teams.index') }}" class="btn btn-g">← Indietro</a>
        </x-slot:actions>
    </x-page-header>

    <x-panel padded>
        <form action="{{ route('teams.store') }}" method="POST">
            @csrf

            <div class="form-row">
                <x-form-group label="Nome team" name="name" required>
                    <input name="name" class="form-in @error('name') is-invalid @enderror"
                           value="{{ old('name') }}" placeholder="Es. Team Frontend">
                </x-form-group>
                <x-form-group label="Stato" name="is_active">
                    <select name="is_active" class="form-sel">
                        <option value="1" {{ old('is_active', '1') == '1' ? 'selected' : '' }}>Attivo</option>
                        <option value="0" {{ old('is_active') == '0' ? 'selected' : '' }}>Inattivo</option>
                    </select>
                </x-form-group>
            </div>

            <div class="form-row full">
                <x-form-group label="Descrizione" name="description">
                    <textarea name="description" class="form-ta @error('description') is-invalid @enderror"
                              rows="3">{{ old('description') }}</textarea>
                </x-form-group>
            </div>

            <div class="sec-lbl u-mt-md">Membri</div>
            <div class="team-members-list">
                @foreach($users as $user)
                <label class="team-member-row hover-bg">
                    <input type="checkbox" name="members[]" value="{{ $user->id }}"
                           id="member_{{ $user->id }}"
                           {{ in_array($user->id, old('members', [])) ? 'checked' : '' }}
                           class="team-member-checkbox">
                    <div class="u-flex-1">
                        <div class="u-text-sm u-text-strong">{{ $user->name }}</div>
                        <div class="u-font-mono u-text-tiny u-text-muted">
                            {{ $user->role->value }}
                            @if($user->primary_specialization) · {{ $user->primary_specialization }}@endif
                        </div>
                    </div>
                    <select name="roles[{{ $user->id }}]"
                            class="form-sel team-member-role-sel">
                        <option value="member"  {{ old("roles.{$user->id}") === 'member'  ? 'selected' : '' }}>Membro</option>
                        <option value="lead"    {{ old("roles.{$user->id}") === 'lead'    ? 'selected' : '' }}>Lead</option>
                        <option value="support" {{ old("roles.{$user->id}") === 'support' ? 'selected' : '' }}>Support</option>
                    </select>
                </label>
                @endforeach
            </div>

            <div class="modal-ft u-section-sep">
                <a href="{{ route('teams.index') }}" class="btn btn-g">Annulla</a>
                <button type="submit" class="btn btn-p">Salva Team</button>
            </div>
        </form>
    </x-panel>
</x-app-layout>
