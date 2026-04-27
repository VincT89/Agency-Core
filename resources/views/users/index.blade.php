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
        <div style="background: rgba(200,16,46,.08); border: 1px solid rgba(200,16,46,.25); border-radius: var(--r); padding: 20px; margin-bottom: 24px;">
            <div style="color: var(--accent); font-weight: 700; margin-bottom: 8px;">
                Password Rigenerata con Successo
            </div>
            <div style="margin-bottom: 12px; color: var(--text);">
                Ecco la nuova password per <strong>{{ session('temp_password')['user'] }}</strong>.
                <br>
                <span style="font-size: 13px; color: var(--text3);">Mostra questa password all'utente. Non verrà mostrata di nuovo.</span>
            </div>
            <div style="font-family: var(--mono); background: var(--bg); padding: 12px 16px; border: 1px solid rgba(200,16,46,.15); border-radius: 4px; font-size: 16px; font-weight: 500; color: var(--text); display: inline-block;">
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
                        <div style="font-size:12px;text-transform:uppercase;font-family:var(--mono)">
                            {{ $user->role->value }}
                        </div>
                    </td>
                    <td><x-badge :status="$user->status" :label="$user->status_label" /></td>
                    <td style="display:flex;gap:6px">
                        <a href="{{ route('users.edit', $user) }}" class="btn-icon">✎</a>
                        
                        @if($user->id !== auth()->id())
                            <form action="{{ route('users.reset-password', $user) }}" method="POST" onsubmit="return confirm('Forzare il reset di password e generarne una nuova temporanea per questo utente?')">
                                @csrf
                                <button type="submit" class="btn-icon" title="Reset Password" style="color:var(--orange)">⟲</button>
                            </form>

                            <form action="{{ route('users.toggle-status', $user) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn-icon" title="Toggle Status" style="color:var(--text3)">
                                    {{ $user->status === 'active' ? '⊘' : '◎' }}
                                </button>
                            </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" style="text-align:center;color:var(--text3);padding:32px">Nessun utente trovato</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-panel>
</x-app-layout>