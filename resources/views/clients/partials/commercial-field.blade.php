@if(auth()->user()->canManageSystem())
    <div class="form-row full">
        <x-form-group label="Commerciale di riferimento" name="commercial_user_id">
            <select id="field-commercial-user-id" name="commercial_user_id" class="form-sel @error('commercial_user_id') is-invalid @enderror">
                <option value="">Nessun commerciale associato</option>
                @foreach($commercialUsers as $commercialUser)
                    <option value="{{ $commercialUser->id }}" @selected(old('commercial_user_id', $client->commercial_user_id ?? null) == $commercialUser->id)>
                        {{ $commercialUser->name }}{{ $commercialUser->status !== 'active' || !$commercialUser->isCommercial() ? ' (non disponibile per nuove assegnazioni)' : '' }}
                    </option>
                @endforeach
            </select>
            <p class="u-text-meta">Il commerciale associato può aggiornare l’anagrafica, aprire ticket e seguire le attività di questo cliente.</p>
        </x-form-group>
    </div>
@endif
