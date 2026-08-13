<x-app-layout title="Nuovo Utente">
    <x-page-header
        eyebrow="Modulo · Admin"
        
    >
    <x-slot:title><strong>Nuovo</strong> utente</x-slot:title>
        <x-slot:actions>
            <a href="{{ route('users.index') }}" class="btn btn-g">← Indietro</a>
        </x-slot:actions>
    </x-page-header>

    <x-panel padded>
        <form action="{{ route('users.store') }}" method="POST">
            @csrf
            
            <div class="form-row">
                <x-form-group label="Nome Completo" name="name" required>
                    <input name="name" class="form-in @error('name') is-invalid @enderror"
                           value="{{ old('name') }}" placeholder="Es. Mario Rossi" required autocomplete="name">
                </x-form-group>
                <x-form-group label="Email" name="email" required>
                    <input type="email" name="email" class="form-in @error('email') is-invalid @enderror"
                           value="{{ old('email') }}" placeholder="mario.rossi@sodanoconsulting.it" required autocomplete="email">
                </x-form-group>
            </div>

            <div class="form-row">
                <x-form-group label="Ruolo" name="role" required>
                    <select name="role" class="form-sel @error('role') is-invalid @enderror" required>
                        @foreach($roles as $role)
                            <option value="{{ $role->value }}" {{ old('role', 'developer') == $role->value ? 'selected' : '' }}>{{ $role->label() }}</option>
                        @endforeach
                    </select>
                </x-form-group>
                <x-form-group label="Telefono (opzionale)" name="phone">
                    <input name="phone" class="form-in @error('phone') is-invalid @enderror"
                           value="{{ old('phone') }}">
                </x-form-group>
            </div>

            <div class="form-row full">
                <x-form-group label="Specializzazione Principale (opzionale)" name="primary_specialization">
                    <input name="primary_specialization" class="form-in @error('primary_specialization') is-invalid @enderror"
                           value="{{ old('primary_specialization') }}" placeholder="Es. Digital Marketing, SEO, DevOps...">
                </x-form-group>
            </div>

            <div class="form-row">
                <x-form-group label="Password temporanea" name="password" required>
                    <x-password-input id="password" name="password" :class="$errors->has('password') ? 'is-invalid' : ''" required autocomplete="new-password" />
                </x-form-group>
                <x-form-group label="Conferma password temporanea" name="password_confirmation" required>
                    <x-password-input id="password_confirmation" name="password_confirmation" required autocomplete="new-password" />
                </x-form-group>
            </div>

            <div class="modal-ft u-section-sep">
                <a href="{{ route('users.index') }}" class="btn btn-g">Annulla</a>
                <button type="submit" class="btn btn-p">Salva Utente</button>
            </div>
        </form>
    </x-panel>
</x-app-layout>
