<div>
    <div style="margin-bottom:15px">
        <a href="{{ route('photography.shooting.index') }}" wire:navigate style="color:var(--text3);font-size:12px;text-decoration:none">← Torna ai miei shooting</a>
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
        
        <!-- Main Column -->
        <div style="display:flex; flex-direction:column; gap:24px;">
            <x-panel title="Dettagli Shooting" dot="var(--purple)">
                <div style="padding:24px;">
                    <div class="g-shoot-2col">
                        <div>
                            <div style="font-size:12px; color:var(--text3); text-transform:uppercase; font-weight:600; letter-spacing:0.5px; margin-bottom:4px;">Progetto</div>
                            <div style="font-weight:500; color:var(--text1);">{{ $shoot->project->name }}</div>
                        </div>
                    </div>
                    
                    @if($shoot->location)
                        <div style="margin-bottom:24px;">
                            <div style="font-size:12px; color:var(--text3); text-transform:uppercase; font-weight:600; letter-spacing:0.5px; margin-bottom:4px;">Location</div>
                            <div style="font-size:14px; color:var(--text1);">{{ $shoot->location }}</div>
                        </div>
                    @endif
                </div>
            </x-panel>
            
            <!-- Slots -->
            <x-panel title="Disponibilità" dot="var(--blue)">
                <div style="padding:24px;">
                    @php
                        $canRespond = $shoot->status->value === 'waiting_photographer';
                    @endphp
                    
                    @if($canRespond)
                        <p style="font-size:13px; color:var(--text2); margin-bottom:16px;">
                            Il team ha proposto i seguenti slot temporali. Scegline uno per confermare la tua disponibilità, oppure rifiutali tutti se non puoi partecipare in nessuna delle date proposte.
                        </p>
                    @endif

                    <x-shooting.slot-list :shoot="$shoot" :interactive="$canRespond" :showWarning="$canRespond" />
                </div>
            </x-panel>
        </div>
        
    </div>
</div>
