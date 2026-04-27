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

            <div class="sec-lbl" style="margin-top:16px">Membri</div>
            <div style="border:1px solid var(--line);border-radius:var(--r);overflow:hidden;margin-bottom:16px">
                @foreach($users as $user)
                <label style="display:flex;align-items:center;gap:12px;padding:10px 14px;
                               border-bottom:1px solid var(--line);cursor:pointer;transition:background .12s"
                       onmouseover="this.style.background='var(--bg2)'"
                       onmouseout="this.style.background=''">
                    <input type="checkbox" name="members[]" value="{{ $user->id }}"
                           id="member_{{ $user->id }}"
                           {{ in_array($user->id, old('members', [])) ? 'checked' : '' }}
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
                        <option value="member"  {{ old("roles.{$user->id}") === 'member'  ? 'selected' : '' }}>Membro</option>
                        <option value="lead"    {{ old("roles.{$user->id}") === 'lead'    ? 'selected' : '' }}>Lead</option>
                        <option value="support" {{ old("roles.{$user->id}") === 'support' ? 'selected' : '' }}>Support</option>
                    </select>
                </label>
                @endforeach
            </div>

            <div class="modal-ft" style="border-top:1px solid var(--line);padding-top:16px">
                <a href="{{ route('teams.index') }}" class="btn btn-g">Annulla</a>
                <button type="submit" class="btn btn-p">Salva Team</button>
            </div>
        </form>
    </x-panel>
</x-app-layout>
