@if(auth()->user()->canManageSystem())
    <div class="form-row full">
        <x-form-group label="Commerciale di riferimento" name="commercial_user_id">
            <select name="commercial_user_id" class="form-sel @error('commercial_user_id') is-invalid @enderror">
                <option value="">Nessun commerciale associato</option>
                @foreach($commercialUsers as $commercialUser)
                    <option value="{{ $commercialUser->id }}" @selected(old('commercial_user_id', $client->commercial_user_id ?? null) == $commercialUser->id)>
                        {{ $commercialUser->name }}{{ $commercialUser->status !== 'active' || !$commercialUser->isCommercial() ? ' (non disponibile per nuove assegnazioni)' : '' }}
                    </option>
                @endforeach
            </select>
            <p class="u-text-meta">Il commerciale associato può selezionare questo cliente quando apre un ticket.</p>
        </x-form-group>
    </div>
@endif
