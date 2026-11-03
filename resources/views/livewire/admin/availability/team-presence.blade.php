<div wire:poll.20s class="presence-team">
    <x-panel title="Disponibilità del team">
        <x-slot:headerActions>
            <a href="{{ route('admin.availability.index') }}" wire:navigate class="btn btn-g btn-sm">Fasce e storico</a>
        </x-slot:headerActions>
        <div class="presence-team-list" role="region" aria-label="Presenza degli operatori" tabindex="0">
            @forelse($operators as $operator)
                <article class="presence-operator" wire:key="presence-operator-{{ $operator['id'] }}">
                    <div class="presence-operator-person">
                        <strong>{{ $operator['name'] }}</strong>
                        <span class="presence-status" data-state="{{ $operator['state'] }}"><span class="presence-dot" aria-hidden="true"></span>{{ $operator['label'] }}</span>
                    </div>
                    <dl class="presence-operator-times">
                        <div><dt>Fascia prevista</dt><dd>{{ implode(', ', $operator['slots']) ?: 'Non indicata' }}</dd></div>
                        <div><dt>Ultimo accesso</dt><dd>{{ $operator['accessed'] ?? 'Non registrato' }}</dd></div>
                        <div><dt>Disponibile dalle</dt><dd>{{ $operator['started'] ?? 'Non confermata' }}</dd></div>
                        <div><dt>{{ $operator['estimated_end'] ? 'Ultimo segnale' : 'Uscita' }}</dt><dd>{{ $operator['ended'] ?? 'Non registrata' }}</dd></div>
                        <div><dt>Disponibilità oggi</dt><dd>{{ $operator['available'] }}</dd></div>
                        <div><dt>Oltre fascia</dt><dd>{{ $operator['extra'] }}</dd></div>
                    </dl>
                </article>
            @empty
                <p class="presence-empty">Nessun operatore attivo.</p>
            @endforelse
        </div>
    </x-panel>
</div>
