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
                           value="{{ old('name') }}" placeholder="Es. Mario Rossi">
                </x-form-group>
                <x-form-group label="Email" name="email" required>
                    <input type="email" name="email" class="form-in @error('email') is-invalid @enderror"
                           value="{{ old('email') }}" placeholder="mario.rossi@sodanoconsulting.it">
                </x-form-group>
            </div>

            <div class="form-row">
                <x-form-group label="Ruolo" name="role" required>
                    <select name="role" class="form-sel @error('role') is-invalid @enderror">
                        @foreach($roles as $role)
                            <option value="{{ $role->value }}" {{ old('role', 'developer') == $role->value ? 'selected' : '' }}>{{ ucfirst($role->value) }}</option>
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
                <x-form-group label="Password Temporanea" name="password" required>
                    <input type="password" name="password" class="form-in @error('password') is-invalid @enderror" required>
                </x-form-group>
                <x-form-group label="Conferma Password Temporanea" name="password_confirmation" required>
                    <input type="password" name="password_confirmation" class="form-in" required>
                </x-form-group>
            </div>

            <div class="modal-ft u-section-sep">
                <a href="{{ route('users.index') }}" class="btn btn-g">Annulla</a>
                <button type="submit" class="btn btn-p">Salva Utente</button>
            </div>
        </form>
    </x-panel>
</x-app-layout>
