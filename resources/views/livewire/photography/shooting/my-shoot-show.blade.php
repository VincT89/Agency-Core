<div>
    <div class="u-mb-sm">
        <a href="{{ route('photography.shooting.index') }}" wire:navigate class="u-text-muted u-text-sm u-no-underline">← Torna ai miei shooting</a>
    </div>

    <x-page-header eyebrow="Fotografia">
        <x-slot:title>
            <strong>{{ $shoot->title }}</strong>
        </x-slot:title>
        <x-slot name="actions">
            <x-shooting.status-badge :status="$shoot->status" context="photography" />
        </x-slot>
    </x-page-header>

    <div class="g-shoot-detail">
        
        {{-- Main Column --}}
        <div class="u-flex-col u-gap-md">
            <x-panel title="Dettagli Shooting" dot="var(--purple)">
                <div class="u-p-lg">
                    <div class="g-shoot-2col">
                        <div>
                            <div class="u-text-sm u-text-muted u-uppercase u-text-strong u-tracking-wide u-mb-xs">Progetto</div>
                            <div class="u-text-strong u-text-primary">{{ $shoot->project->name }}</div>
                        </div>
                    </div>
                    
                    @if($shoot->location)
                        <div class="u-mb-lg">
                            <div class="u-text-sm u-text-muted u-uppercase u-text-strong u-tracking-wide u-mb-xs">Location</div>
                            <div class="u-text-md u-text-primary">{{ $shoot->location }}</div>
                        </div>
                    @endif
                </div>
            </x-panel>
            
            {{-- Slots --}}
            <x-panel title="Disponibilità" dot="var(--blue)">
                <div class="u-p-lg">
                    @php
                        $canRespond = $shoot->status->value === 'waiting_photographer';
                    @endphp
                    
                    @if($canRespond)
                        <p class="u-text-sm u-text-secondary u-mb-md">
                            Il team ha proposto i seguenti slot temporali. Scegline uno per confermare la tua disponibilità, oppure rifiutali tutti se non puoi partecipare in nessuna delle date proposte.
                        </p>
                    @endif

                    <x-shooting.slot-list :shoot="$shoot" :interactive="$canRespond" :showWarning="$canRespond" />
                </div>
            </x-panel>
        </div>
        
    </div>
</div>
