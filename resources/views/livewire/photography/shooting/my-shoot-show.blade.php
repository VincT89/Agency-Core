<div>
    <div class="page-back-row">
        <a href="{{ route('photography.shooting.index') }}"
           wire:navigate
           class="btn btn-g btn-sm">
            <i data-lucide="arrow-left" class="u-icon-sm" aria-hidden="true"></i>
            Torna ai miei shooting
        </a>
    </div>

    <x-page-header eyebrow="Fotografia">
        <x-slot:title><strong>{{ $shoot->title }}</strong></x-slot:title>
        <x-slot name="actions">
            <x-shooting.status-badge :status="$shoot->status" context="photography" />
        </x-slot>
    </x-page-header>

    <div class="g-shoot-detail">
        <div class="u-flex-col u-gap-md">
            <x-panel title="Dettagli Shooting" dot="var(--purple)">
                <div class="u-p-lg">
                    <div class="g-shoot-2col">
                        <div>
                            @if($shoot->project)
                                <div class="shooting-lbl-caps">Progetto</div>
                                <div class="shooting-text-val-bold">{{ $shoot->project->name }}</div>
                            @elseif($shoot->marketingCampaign)
                                <div class="shooting-lbl-caps">Campagna Marketing</div>
                                <div class="shooting-text-val-bold">
                                    {{ $shoot->marketingCampaign->client->name }}
                                    · {{ $shoot->marketingCampaign->name }}
                                </div>
                            @endif
                        </div>
                        @if($shoot->location)
                            <div>
                                <div class="shooting-lbl-caps">Luogo</div>
                                <div class="shooting-text-val">{{ $shoot->location }}</div>
                            </div>
                        @endif
                    </div>

                    <div class="g-shoot-2col shooting-mb-0">
                        <div>
                            <div class="shooting-lbl-caps">Indicazioni da comunicare al cliente</div>
                            <div class="shoot-note-box">
                                {{ $shoot->client_notes ?: 'Nessuna indicazione specifica.' }}
                            </div>
                        </div>
                        <div>
                            <div class="shooting-lbl-caps">Note operative</div>
                            <div class="shoot-note-box purple">
                                {{ $shoot->internal_notes ?: 'Nessuna nota operativa.' }}
                            </div>
                        </div>
                    </div>
                </div>
            </x-panel>

            <x-panel title="Disponibilità" dot="var(--blue)">
                <div class="u-p-lg">
                    @php
                        $canRespond = $shoot->status === \App\Enums\Shooting\ShootStatus::WaitingPhotographer;
                    @endphp

                    @if($canRespond)
                        <p class="shooting-desc-text">
                            Scegli una delle date proposte per confermare la tua disponibilità.
                            Se nessuna è possibile, rifiuta la proposta e indica il motivo.
                        </p>
                    @elseif($shoot->status === \App\Enums\Shooting\ShootStatus::WaitingClient)
                        <p class="shooting-desc-text">
                            Hai confermato la disponibilità. Il marketing sta contattando il cliente.
                        </p>
                    @endif

                    @error('slot')
                        <div class="shooting-err-summary">{{ $message }}</div>
                    @enderror

                    <x-shooting.slot-list
                        :shoot="$shoot"
                        :interactive="$canRespond"
                        :showWarning="$canRespond" />
                </div>
            </x-panel>
        </div>

        <div>
            <x-panel title="Avanzamento" dot="var(--green)">
                <div class="u-p-lg">
                    <x-shooting.workflow-timeline :shoot="$shoot" />
                </div>
            </x-panel>
        </div>
    </div>
</div>
