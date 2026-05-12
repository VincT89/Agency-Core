<form method="post" action="{{ route('password.update') }}">
    @csrf
    @method('put')

    <div class="form-row full">
        <x-form-group label="Password Attuale" name="current_password" required>
            <input id="update_password_current_password" name="current_password" type="password" class="form-in @error('current_password', 'updatePassword') is-invalid @enderror" autocomplete="current-password" />
            @error('current_password', 'updatePassword')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </x-form-group>
    </div>

    <div class="form-row full">
        <x-form-group label="Nuova Password" name="password" required>
            <input id="update_password_password" name="password" type="password" class="form-in @error('password', 'updatePassword') is-invalid @enderror" autocomplete="new-password" />
            @error('password', 'updatePassword')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </x-form-group>
    </div>

    <div class="form-row full">
        <x-form-group label="Conferma Password" name="password_confirmation" required>
            <input id="update_password_password_confirmation" name="password_confirmation" type="password" class="form-in @error('password_confirmation', 'updatePassword') is-invalid @enderror" autocomplete="new-password" />
            @error('password_confirmation', 'updatePassword')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </x-form-group>
    </div>

    <div class="modal-ft u-mt-md">
        <button type="submit" class="btn btn-p">Aggiorna Password</button>
        @if (session('status') === 'password-updated')
            <span class="u-text-sm u-text-green u-ml-sm">Aggiornata.</span>
        @endif
    </div>
</form>