<x-app-layout title="Attività Recenti">
    <x-page-header  eyebrow="Amministrazione" >
    <x-slot:title>Attività Recenti</x-slot:title>
</x-page-header>

    <x-panel class="u-mb-md">
    <x-slot:title>Filtri</x-slot:title>
        <form action="{{ route('audit-logs.index') }}" method="GET" class="g-3col u-items-end">
            <div>
                <label class="form-lbl">Entità</label>
                <select name="auditable_type" class="form-in">
                    <option value="">Tutte le entità</option>
                    <option value="Ticket" {{ request('auditable_type') === 'Ticket' ? 'selected' : '' }}>Ticket</option>
                    <option value="Invoice" {{ request('auditable_type') === 'Invoice' ? 'selected' : '' }}>Fatture</option>
                    <option value="Payment" {{ request('auditable_type') === 'Payment' ? 'selected' : '' }}>Pagamenti</option>
                    <option value="Client" {{ request('auditable_type') === 'Client' ? 'selected' : '' }}>Clienti</option>
                    <option value="Project" {{ request('auditable_type') === 'Project' ? 'selected' : '' }}>Progetti</option>
                    <option value="Task" {{ request('auditable_type') === 'Task' ? 'selected' : '' }}>Task</option>
                    <option value="User" {{ request('auditable_type') === 'User' ? 'selected' : '' }}>Utenti</option>
                </select>
            </div>
            <div>
                <label class="form-lbl">Utente</label>
                <select name="user_id" class="form-in">
                    <option value="">Tutti gli utenti</option>
                    @foreach(\App\Models\User::orderBy('name')->get() as $u)
                        <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="u-flex u-gap-sm">
                <button type="submit" class="btn btn-p">Filtra</button>
                <a href="{{ route('audit-logs.index') }}" class="btn btn-g">Reset</a>
            </div>
        </form>
    </x-panel>

    <x-panel title="Elenco Globale Attività">
        <x-audit-timeline :logs="$logs" />
        
        <div class="u-mt-md">
            {{ $logs->links() }}
        </div>
    </x-panel>
</x-app-layout>
