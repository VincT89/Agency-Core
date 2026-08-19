<div>
    <div class="page-back-row">
        <a href="{{ route('admin.shooting.index') }}"
           wire:navigate
           class="btn btn-g btn-sm">
            <i data-lucide="arrow-left" class="u-icon-sm" aria-hidden="true"></i>
            Torna agli shooting
        </a>
    </div>

    <x-page-header eyebrow="Amministrazione">
        <x-slot:title>
            <strong>{{ $shoot->title }}</strong>
            <span class="u-text-base u-text-muted u-font-normal u-ml-sm">{{ $shoot->code }}</span>
        </x-slot:title>
        <x-slot name="actions">
            <x-shooting.status-badge :status="$shoot->status" context="admin" />
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
                        <div>
                            <div class="shooting-lbl-caps">Fotografo</div>
                            @if($shoot->photographer)
                                <div class="shooting-flex-center-gap8">
                                    <div class="avatar-sm">{{ substr($shoot->photographer->name, 0, 1) }}</div>
                                    <span class="shooting-text-val-bold">{{ $shoot->photographer->name }}</span>
                                </div>
                            @else
                                <span class="shooting-unassigned">Non assegnato</span>
                            @endif
                        </div>
                    </div>

                    @if($shoot->location)
                        <div class="shooting-mb-24">
                            <div class="shooting-lbl-caps">Luogo</div>
                            <div class="shooting-text-val">{{ $shoot->location }}</div>
                        </div>
                    @endif

                    <div class="g-shoot-2col shooting-mb-0">
                        <div>
                            <div class="shooting-lbl-caps">Note da comunicare al cliente</div>
                            <div class="shoot-note-box">
                                {{ $shoot->client_notes ?: 'Nessuna nota per il cliente.' }}
                            </div>
                        </div>
                        <div>
                            <div class="shooting-lbl-caps">Note interne</div>
                            <div class="shoot-note-box purple">
                                {{ $shoot->internal_notes ?: 'Nessuna nota interna.' }}
                            </div>
                        </div>
                    </div>
                </div>
            </x-panel>

            @if($shoot->status === \App\Enums\Shooting\ShootStatus::WaitingClient)
                <x-panel title="Comunicazione e risposta cliente" dot="var(--yellow)">
                    <div class="u-p-lg">
                        <p class="shooting-desc-text">
                            Il fotografo ha confermato uno slot. Registra la comunicazione effettuata
                            e poi la risposta ricevuta dal cliente.
                        </p>
                        <x-shooting.client-contact-panel
                            :shoot="$shoot"
                            :communication="$communication"
                            :client-channels="$clientChannels" />
                    </div>
                </x-panel>
            @endif

            @if(in_array($shoot->status, [
                \App\Enums\Shooting\ShootStatus::PhotographerRejected,
                \App\Enums\Shooting\ShootStatus::ClientRejected,
            ], true))
                <x-panel title="Nuova proposta necessaria" dot="var(--yellow)">
                    <div class="u-p-lg">
                        <p class="shooting-desc-text">
                            Il marketing deve aggiornare fotografo e date prima di riaprire la richiesta.
                        </p>
                        <a href="{{ route('social.shooting.show', $shoot) }}" class="btn btn-p">
                            Rivedi la proposta
                        </a>
                    </div>
                </x-panel>
            @endif

            <x-panel title="Date proposte" dot="var(--blue)">
                <div class="u-p-lg">
                    @if($shoot->status === \App\Enums\Shooting\ShootStatus::WaitingPhotographer)
                        <p class="shooting-desc-text">
                            La risposta deve essere inserita dal fotografo assegnato.
                        </p>
                    @endif
                    <x-shooting.slot-list
                        :shoot="$shoot"
                        :interactive="false"
                        :showWarning="false" />
                </div>
            </x-panel>

            @if($shoot->calendarEvent || $shoot->task)
                <x-panel title="Pianificazione creata" dot="var(--green)">
                    <div class="u-p-lg shooting-contact-actions">
                        @if($shoot->calendarEvent)
                            <a href="{{ route('calendar-events.show', $shoot->calendarEvent) }}" class="btn btn-g">
                                Vedi evento nel calendario
                            </a>
                        @endif
                        @if($shoot->task)
                            <a href="{{ route('tasks.show', $shoot->task) }}" class="btn btn-g">
                                Vedi task del fotografo
                            </a>
                        @endif
                    </div>
                </x-panel>
            @endif
        </div>

        <div>
            <x-panel title="Avanzamento" dot="var(--green)">
                <div class="u-p-lg">
                    <x-shooting.workflow-timeline :shoot="$shoot" />
                </div>
            </x-panel>

            <div class="mt-panel">
                <x-panel title="Storico attività" dot="var(--gray)">
                    <div class="u-p-md">
                        @forelse($shoot->auditLogs()->latest()->get() as $log)
                            <x-audit-item :log="$log" />
                        @empty
                            <div class="u-text-muted u-text-sm">Nessuna attività registrata.</div>
                        @endforelse
                    </div>
                </x-panel>
            </div>
        </div>
    </div>
</div>
