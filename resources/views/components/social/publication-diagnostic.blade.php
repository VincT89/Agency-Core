@php
    $diagnostic = app(\App\Domain\Social\Services\PublicationDiagnosticPresenter::class)
        ->present($publication);
    $isPanel = (bool) ($panel ?? false);
@endphp

<div
    class="social-publication-diagnostic {{ $isPanel ? 'is-panel' : 'is-compact' }}"
    data-publication-diagnostic
>
    @if($diagnostic['message'])
        <div class="social-publication-diagnostic-message">
            <strong>Motivo registrato</strong>
            <span>{{ $diagnostic['message'] }}</span>
        </div>
    @endif

    @if($diagnostic['initialization_accepted'] !== null
        || $diagnostic['provider_status']
        || $diagnostic['provider_code']
        || $diagnostic['request_reference']
        || $diagnostic['http_status'])
        <dl class="social-publication-diagnostic-meta">
            @if($diagnostic['initialization_accepted'] !== null)
                <div>
                    <dt>Richiesta iniziale</dt>
                    <dd>
                        {{ $diagnostic['initialization_accepted']
                            ? 'Accettata da TikTok'
                            : 'Non risulta confermata da TikTok' }}
                    </dd>
                </div>
            @endif

            @if($diagnostic['provider_status'])
                <div>
                    <dt>Stato TikTok</dt>
                    <dd>
                        {{ $diagnostic['provider_status_label'] }}
                        <span class="social-publication-diagnostic-code">{{ $diagnostic['provider_status'] }}</span>
                    </dd>
                </div>
            @endif

            @if($diagnostic['provider_code'])
                <div>
                    <dt>Codice TikTok</dt>
                    <dd class="social-publication-diagnostic-code">{{ $diagnostic['provider_code'] }}</dd>
                </div>
            @endif

            @if($diagnostic['http_status'])
                <div>
                    <dt>Risposta HTTP</dt>
                    <dd>{{ $diagnostic['http_status'] }}</dd>
                </div>
            @endif

            @if($diagnostic['request_reference'])
                <div>
                    <dt>Riferimento TikTok</dt>
                    <dd class="social-publication-diagnostic-code">{{ $diagnostic['request_reference'] }}</dd>
                </div>
            @endif
        </dl>
    @endif

    @if($diagnostic['initialization_accepted'] === true)
        <div class="social-publication-diagnostic-warning">
            Prima di riprovare, verifica il profilo TikTok: la richiesta iniziale era stata accettata e un nuovo invio potrebbe creare un duplicato.
        </div>
    @endif
</div>
