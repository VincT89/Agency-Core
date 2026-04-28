<div>
    <x-panel title="Costruttore Piano Editoriale" padded>
        <p class="mkt-subtitle">In questa sezione sarà possibile gestire in modo avanzato gli slot del piano editoriale prima dell'invio definitivo a n8n (drag & drop, bulk edit).</p>
        
        <div class="mkt-wizard-footer" style="border:none;">
            <a href="{{ route('marketing-projects.show', $plan->marketing_project_id) }}" wire:navigate class="btn btn-secondary">Torna al Progetto</a>
        </div>
    </x-panel>
</div>
