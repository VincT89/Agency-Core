<x-guest-layout>
    <div class="u-min-h-screen u-flex-center u-bg-gray-100 u-p-md">
        <div class="u-w-full u-max-w-md u-bg-white u-rounded u-shadow-md u-p-lg u-text-center">
            
            <div class="u-mb-md">
                <i data-lucide="check-circle" class="u-icon-lg u-text-success"></i>
            </div>

            <h2 class="u-text-h3 u-text-strong u-mb-xs">Connessione Riuscita!</h2>
            <p class="u-text-muted u-mb-md">
                Grazie {{ $client->name }}, abbiamo collegato con successo l'account <strong>{{ $pageName }}</strong>.
            </p>

            <div class="u-alert-success u-text-left u-mb-lg">
                <p class="u-m-0">L'agenzia ora può pubblicare i contenuti approvati direttamente sui tuoi canali social.</p>
            </div>

            <p class="u-text-sm u-text-muted">
                Puoi chiudere questa finestra.
            </p>
        </div>
    </div>
</x-guest-layout>
