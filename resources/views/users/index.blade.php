<x-app-layout title="Gestione Utenti">
    <x-page-header
        eyebrow="Modulo · Admin"
        
        :meta="$users->count() . ' totali'"
    >
    <x-slot:title><strong>Utenti</strong></x-slot:title>
        <x-slot:actions>
            <a href="{{ route('users.create') }}" class="btn btn-p">+ Nuovo utente</a>
        </x-slot:actions>
    </x-page-header>

    @if(session('temp_password'))
        <div class="u-alert-danger u-mb-xl">
            <div class="u-text-accent u-text-strong u-mb-sm">
                Password Rigenerata con Successo
            </div>
            <div class="u-mb-sm u-text-strong">
                Ecco la nuova password per <strong>{{ session('temp_password')['user'] }}</strong>.
            </div>
            <span class="u-text-sm u-text-muted">Mostra questa password all'utente. Non verrà mostrata di nuovo.</span>
            <br>
            <div class="new-password-display">
                {{ session('temp_password')['password'] }}
            </div>
        </div>
    @endif

    <x-panel>
        <table class="t-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Ruolo</th>
                    <th>Stato</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr>
                    <td class="name-col">{{ $user->name }}</td>
                    <td class="mono-col">{{ $user->email }}</td>
                    <td>
                        <div class="u-text-sm u-uppercase u-font-mono">
                            {{ $user->role->value }}
                        </div>
                    </td>
                    <td><x-badge :status="$user->status" :label="$user->status_label" /></td>
                    <td class="u-flex u-gap-xs">
                        <a href="{{ route('users.edit', $user) }}" class="btn-icon">✎</a>
                        
                        @if($user->id !== auth()->id())
                            <form action="{{ route('users.reset-password', $user) }}" method="POST" onsubmit="return confirm('Forzare il reset di password e generarne una nuova temporanea per questo utente?')">
                                @csrf
                                <button type="submit" class="btn-icon u-text-warning" title="Reset Password">⟲</button>
                            </form>

                            <form action="{{ route('users.toggle-status', $user) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn-icon u-text-muted" title="Toggle Status">
                                    {{ $user->status === 'active' ? '⊘' : '◎' }}
                                </button>
                            </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="u-text-center u-text-muted u-p-xl">Nessun utente trovato</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-panel>
</x-app-layout>