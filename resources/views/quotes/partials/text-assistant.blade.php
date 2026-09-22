<x-form-group label="Descrizione progetto e preventivo" name="introduction"><textarea id="introduction" name="introduction" x-model="introduction" class="form-ta" rows="3" maxlength="10000"></textarea></x-form-group>
<details class="quote-ai">
    <summary>Revisione dei testi con AI</summary>
    <p class="quote-help">Invia a OpenAI descrizione del progetto, nomi e testi dei servizi. Potrai confrontare la proposta prima di applicarla. Prezzi, tempi di consegna e condizioni nei campi dedicati restano invariati.</p>
    <label for="ai_instructions">Indicazioni di stile per questa offerta</label>
    <textarea id="ai_instructions" name="ai_instructions" x-model="instructions" class="form-ta" rows="2" maxlength="1500" placeholder="Ad esempio: tono professionale e frasi brevi"></textarea>
    @if(filled(config('quotes.ai.key')))
        <button type="button" class="btn btn-g" @click="improve()" :disabled="aiBusy" x-text="aiBusy ? 'Preparazione proposta...' : 'Migliora testi con AI'"></button>
    @else
        <button type="button" class="btn btn-g" disabled aria-describedby="quote-ai-unavailable">Migliora testi con AI</button>
        <p id="quote-ai-unavailable" class="quote-help">Revisione AI da attivare. L’amministratore deve configurare il collegamento OpenAI del gestionale.</p>
    @endif
    <section x-show="suggestion" x-cloak class="quote-ai-preview" x-ref="aiPreview" tabindex="-1" aria-label="Anteprima dei testi AI">
        <h3>Confronta i testi</h3>
        <div class="quote-ai-comparison"><div><h4>Descrizione originale</h4><p x-text="introduction"></p></div><div><h4>Proposta AI</h4><p x-text="suggestion?.introduction"></p></div></div>
        <template x-for="(proposed, index) in (suggestion?.items || [])" :key="index"><div>
            <h4 x-text="items[index]?.name"></h4>
            <div class="quote-ai-comparison"><div><strong>Originale</strong><p x-text="items[index]?.summary"></p><p x-text="items[index]?.description"></p></div><div><strong>Proposta AI</strong><p x-text="proposed.summary"></p><p x-text="proposed.description"></p></div></div>
        </div></template>
        <div class="commercial-actions"><button type="button" class="btn btn-p" @click="applySuggestion()">Applica i testi proposti</button><button type="button" class="btn btn-g" @click="suggestion = null">Scarta proposta</button></div>
    </section>
</details>
