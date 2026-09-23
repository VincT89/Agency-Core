<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Quote;
use App\Models\QuoteService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QuoteDocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        $this->withoutVite();
        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->client = Client::factory()->create(['company_name' => 'Cliente originale', 'address' => 'Indirizzo originale']);
        $this->actingAs($this->admin);
    }

    private function item(): array
    {
        return ['name' => 'Design', 'summary' => 'Studio del logo', 'description' => 'Studio del logo e consegna dei vettoriali.',
            'delivery_terms' => '45 giorni dalla conferma', 'quantity' => '2', 'unit_price' => '150.50'];
    }

    private function payload(): array
    {
        return ['client_id' => $this->client->id, 'title' => 'Identità del cliente', 'document_reference' => 'N03405',
            'document_date' => '2026-09-21', 'introduction' => 'Il progetto comprende i servizi descritti.',
            'payment_terms' => '40% acconto, 60% consegna', 'notes' => 'Condizioni concordate.', 'price_note' => 'IVA esclusa',
            'ai_instructions' => 'Tono professionale',
            'issuer' => ['legal_name' => 'Agenzia originale', 'address' => 'Sede originale'], 'items' => [$this->item()]];
    }

    private function save(): Quote
    {
        $this->post(route('quotes.store'), $this->payload())->assertSessionHasNoErrors()->assertRedirect();

        return Quote::latest('id')->firstOrFail();
    }

    public function test_reusing_an_offer_prefills_a_new_editable_form_without_copying_its_identity(): void
    {
        $source = $this->save();
        $source->update(['status' => 'accepted', 'accepted_at' => now(), 'client_snapshot' => ['name' => 'Anagrafica storica']]);
        $before = $source->fresh()->getRawOriginal();
        $otherClient = Client::factory()->create();

        foreach ([UserRole::Admin, UserRole::Administration] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(route('quotes.create', ['from_quote_id' => $source->id]))->assertOk()
                ->assertViewHas('quote', null)->assertViewHas('ticket', null)
                ->assertViewHas('sourceQuote', fn ($quote) => $quote->id === $source->id && $quote->items->sole()->summary === 'Studio del logo')
                ->assertViewHas('client', fn ($client) => $client->id === $source->client_id)
                ->assertSee('40% acconto, 60% consegna')->assertSee('Condizioni concordate.')
                ->assertSee('name="document_reference" class="form-in" value=""', false)
                ->assertSee('name="document_date" class="form-in" type="date" value="'.now()->toDateString().'"', false)
                ->assertDontSee('Sede originale')->assertDontSee('Anagrafica storica');
            $this->get(route('quotes.create', ['from_quote_id' => $source->id, 'client_id' => $otherClient->id]))->assertOk()
                ->assertViewHas('client', fn ($client) => $client->id === $otherClient->id);
        }

        $this->assertDatabaseCount('quotes', 1);
        $this->assertSame($before, $source->fresh()->getRawOriginal());
    }

    public function test_reusing_an_offer_obeys_permissions_and_excludes_deleted_sources(): void
    {
        $source = $this->save();
        $commercial = User::factory()->create(['role' => UserRole::Commercial]);
        $this->client->update(['commercial_user_id' => $commercial->id]);
        $source->update(['status' => 'presented']);
        $this->actingAs($commercial)->get(route('quotes.show', $source))->assertOk()->assertDontSee('Usa come modello');
        $this->get(route('quotes.create', ['from_quote_id' => $source->id]))->assertForbidden();
        $this->actingAs($this->admin);
        $source->delete();
        $this->get(route('quotes.create', ['from_quote_id' => $source->id]))->assertNotFound();
        $this->assertDatabaseCount('quotes', 1);
    }

    public function test_finance_roles_can_configure_save_and_preview_quotes(): void
    {
        foreach ([UserRole::Admin, UserRole::Administration] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $quote = $this->save();
            $this->assertSame('301.00', $quote->total);
            $this->assertSame('N03405', $quote->document_reference);
            $this->assertSame('2026-09-21', $quote->document_date->format('Y-m-d'));
            $this->assertSame('Tono professionale', $quote->ai_instructions);
            $this->assertSame('Studio del logo', $quote->items->sole()->summary);
            $this->get(route('quotes.edit', $quote))->assertOk()->assertSee('Salva voce in libreria')->assertSee('Sede originale');
            $this->get(route('quotes.document', $quote))->assertOk()->assertSeeInOrder(['Agenzia originale', 'Cliente originale', 'Categorie servizi', 'Descrizione Progetto e Preventivo', 'Forma di pagamento:'])
                ->assertSee('301,00 €')->assertSee('45 giorni dalla conferma')->assertSee('Bozza')->assertDontSee('Tono professionale');
            $data = $this->payload();
            unset($data['client_id']);
            $this->put(route('quotes.update', $quote), $data + ['after_save' => 'preview'])->assertRedirect(route('quotes.document', $quote));
        }
    }

    public function test_presented_document_preserves_parties_and_revisions_copy_configured_fields(): void
    {
        $quote = $this->save();
        $this->post(route('quotes.present', $quote))->assertRedirect();
        $this->client->update(['company_name' => 'Cliente cambiato', 'address' => 'Nuova sede cliente']);
        config(['quotes.issuer.legal_name' => 'Agenzia cambiata']);
        $this->get(route('quotes.document', $quote))->assertOk()->assertSee('Cliente originale')->assertSee('Indirizzo originale')->assertSee('Agenzia originale')->assertDontSee('Cliente cambiato');
        $data = $this->payload();
        unset($data['client_id']);
        $this->put(route('quotes.update', $quote), $data)->assertForbidden();
        $this->post(route('quotes.revise', $quote))->assertRedirect();
        $revision = $quote->nextQuote()->firstOrFail();
        $this->assertSame($quote->introduction, $revision->introduction);
        $this->assertSame($quote->payment_terms, $revision->payment_terms);
        $this->assertSame($quote->issuer_snapshot, $revision->issuer_snapshot);
        $this->assertSame($quote->items->first()->summary, $revision->items->first()->summary);
        $this->assertSame($quote->items->first()->delivery_terms, $revision->items->first()->delivery_terms);
    }

    public function test_saved_services_are_deduplicated_and_independent_of_quotes(): void
    {
        $quote = $this->save();
        $this->postJson(route('quote-services.store'), $this->item())->assertOk();
        $this->postJson(route('quote-services.store'), $this->item())->assertOk();
        $this->assertDatabaseCount('quote_services', 1);
        $saved = QuoteService::sole();
        $this->getJson(route('quote-services.index', ['q' => 'Design']))->assertOk()->assertJsonPath('0.name', 'Design');
        $this->getJson(route('quote-services.index', ['q' => 'inesistente']))->assertOk()->assertExactJson([]);
        $payload = $this->payload();
        unset($payload['client_id']);
        $payload['items'][0]['description'] = 'Personalizzata per il cliente';
        $payload['items'][0]['unit_price'] = '1';
        $this->put(route('quotes.update', $quote), $payload)->assertSessionHasNoErrors();
        $this->assertSame('150.50', $saved->fresh()->unit_price);
        $this->delete(route('quote-services.destroy', $saved))->assertRedirect();
        $this->assertSame('Personalizzata per il cliente', $quote->fresh()->items->sole()->description);
        $this->assertDatabaseCount('quote_services', 0);
    }

    public function test_permissions_protect_library_ai_and_documents(): void
    {
        $commercial = User::factory()->create(['role' => UserRole::Commercial]);
        $this->client->update(['commercial_user_id' => $commercial->id]);
        $quote = $this->save();
        $this->actingAs($commercial)->get(route('quotes.document', $quote))->assertForbidden();
        $quote->update(['status' => 'presented']);
        $this->get(route('quotes.document', $quote))->assertOk();
        $this->actingAs(User::factory()->create(['role' => UserRole::Commercial]))->get(route('quotes.document', $quote))->assertForbidden();
        foreach ([UserRole::Commercial, UserRole::Marketing, UserRole::Developer, UserRole::Photographer] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->getJson(route('quote-services.index'))->assertForbidden();
            $this->postJson(route('quote-services.store'), $this->item())->assertForbidden();
            $this->postJson(route('quotes.text-suggestion'), $this->aiInput())->assertForbidden();
        }
        $this->actingAs($this->admin)->delete(route('quotes.destroy', $quote))->assertRedirect();
        $this->get(route('quotes.document', $quote))->assertNotFound();
    }

    public function test_document_escapes_untrusted_text_and_legacy_quotes_still_render(): void
    {
        $quote = Quote::create(['client_id' => $this->client->id, 'created_by' => $this->admin->id, 'status' => 'draft', 'title' => '<script>unsafe()</script>', 'total' => 0]);
        $quote->items()->create(['name' => 'Servizio precedente', 'description' => '<img src=x onerror=unsafe()>', 'quantity' => 1, 'unit_price' => 0, 'total' => 0, 'sort_order' => 0]);
        $this->get(route('quotes.document', $quote))->assertOk()->assertDontSee('<script>unsafe()', false)->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<img src=x', false)->assertSee('Servizio precedente')->assertSee('Vedi descrizione dettagliata');
    }

    private function aiInput(): array
    {
        return ['introduction' => 'Sito composto da 5 pagine.', 'instructions' => 'Tono professionale',
            'items' => [['name' => 'Web', 'summary' => '5 pagine', 'description' => 'Realizziamo 5 pagine.']]];
    }

    private function aiResponse(array $changes = []): array
    {
        $proposal = array_replace_recursive(['introduction' => 'Realizzazione di un sito composto da 5 pagine.',
            'items' => [['index' => 0, 'summary' => 'Sito di 5 pagine', 'description' => 'Il servizio comprende la realizzazione di 5 pagine.']]], $changes);

        return ['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($proposal)]]]]];
    }

    public function test_ai_returns_only_a_proposal_and_never_persists_or_receives_amounts(): void
    {
        config(['quotes.ai.key' => 'fake-key']);
        Http::fake(['api.openai.com/v1/responses' => Http::response($this->aiResponse())]);
        $quote = $this->save();
        $before = $quote->fresh()->getRawOriginal();
        $this->postJson(route('quotes.text-suggestion'), $this->aiInput())->assertOk()->assertJsonPath('items.0.summary', 'Sito di 5 pagine');
        $this->assertSame($before, $quote->fresh()->getRawOriginal());
        Http::assertSent(function ($request) {
            $input = json_decode($request['input'], true);

            return $request['store'] === false && ! isset($input['client_id']) && ! isset($input['items'][0]['unit_price'])
                && ! isset($input['items'][0]['delivery_terms']) && $request['text']['format']['strict'] === true;
        });
    }

    public function test_ai_rejects_changed_numbers_incomplete_and_invalid_responses(): void
    {
        config(['quotes.ai.key' => 'fake-key']);
        foreach ([
            $this->aiResponse(['introduction' => 'Realizzazione di 10 pagine.']),
            $this->aiResponse(['items' => [['index' => 1]]]),
            ['status' => 'incomplete', 'output' => []],
            ['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'invalid-json']]]]],
        ] as $response) {
            Http::fake(['api.openai.com/v1/responses' => Http::response($response)]);
            $this->postJson(route('quotes.text-suggestion'), $this->aiInput())->assertUnprocessable()->assertJsonValidationErrors('ai');
        }
    }

    public function test_ai_revises_a_sparse_quote_without_filling_empty_service_fields(): void
    {
        config(['quotes.ai.key' => 'fake-key']);
        $data = ['introduction' => 'rifacimento della homepage con animazioni', 'instructions' => 'Tono professionale',
            'items' => [['name' => 'restyling homepage', 'summary' => '', 'description' => '']]];
        Http::fake(['api.openai.com/v1/responses' => Http::response($this->aiResponse([
            'introduction' => 'Rifacimento della homepage con animazioni.',
            'items' => [['index' => 0, 'summary' => 'Restyling della homepage.', 'description' => 'Homepage con animazioni e 2 revisioni.']],
        ]))]);

        $this->postJson(route('quotes.text-suggestion'), $data)->assertOk()
            ->assertJsonPath('introduction', 'Rifacimento della homepage con animazioni.')
            ->assertJsonPath('items.0.summary', '')->assertJsonPath('items.0.description', '');
        $this->assertDatabaseCount('quotes', 0);
        $this->assertDatabaseCount('quote_services', 0);
    }

    public function test_ai_preserves_an_empty_introduction_while_revising_existing_service_texts(): void
    {
        config(['quotes.ai.key' => 'fake-key']);
        $data = $this->aiInput();
        $data['introduction'] = '';
        Http::fake(['api.openai.com/v1/responses' => Http::response($this->aiResponse([
            'introduction' => 'Un nuovo testo con 2 servizi non richiesti.',
        ]))]);

        $this->postJson(route('quotes.text-suggestion'), $data)->assertOk()
            ->assertJsonPath('introduction', '')
            ->assertJsonPath('items.0.summary', 'Sito di 5 pagine');
    }

    public function test_ai_still_rejects_changed_numbers_in_populated_service_fields(): void
    {
        config(['quotes.ai.key' => 'fake-key']);
        foreach (['summary' => 'riepilogo', 'description' => 'descrizione'] as $field => $label) {
            $data = ['introduction' => '', 'items' => [['name' => 'Sito web', 'summary' => '', 'description' => '', $field => 'Sito di 5 pagine']]];
            Http::fake(['api.openai.com/v1/responses' => Http::response($this->aiResponse([
                'introduction' => '',
                'items' => [['index' => 0, 'summary' => '', 'description' => '', $field => 'Sito di 10 pagine']],
            ]))]);

            $response = $this->postJson(route('quotes.text-suggestion'), $data)->assertUnprocessable()->assertJsonValidationErrors('ai');
            $this->assertStringContainsString($label, $response->json('errors.ai.0'));
        }
    }

    public function test_ai_cannot_erase_existing_text_without_numbers(): void
    {
        config(['quotes.ai.key' => 'fake-key']);
        $data = ['introduction' => '', 'items' => [['name' => 'Design', 'summary' => 'Studio del logo', 'description' => '']]];
        Http::fake(['api.openai.com/v1/responses' => Http::response($this->aiResponse([
            'introduction' => '', 'items' => [['index' => 0, 'summary' => '', 'description' => '']],
        ]))]);

        $this->postJson(route('quotes.text-suggestion'), $data)->assertUnprocessable()
            ->assertJsonValidationErrors('ai');
    }

    public function test_ai_missing_key_and_provider_errors_are_safe(): void
    {
        config(['quotes.ai.key' => null]);
        $this->postJson(route('quotes.text-suggestion'), $this->aiInput())->assertUnprocessable()->assertJsonValidationErrors('ai');
        Http::assertNothingSent();
        config(['quotes.ai.key' => 'fake-key']);
        Http::fake(['api.openai.com/v1/responses' => Http::response(['error' => 'Sensitive upstream content'], 401)]);
        $this->postJson(route('quotes.text-suggestion'), $this->aiInput())->assertUnprocessable()->assertDontSee('Sensitive upstream content');
    }

    public function test_ai_rejects_extra_service_properties_and_limits_request_size(): void
    {
        $data = $this->aiInput();
        $data['items'][0]['unit_price'] = 100;
        $this->postJson(route('quotes.text-suggestion'), $data)->assertUnprocessable();
        $data = $this->aiInput();
        $data['items'] = array_fill(0, 20, ['name' => 'Servizio', 'summary' => '', 'description' => str_repeat('a', 3000)]);
        $this->postJson(route('quotes.text-suggestion'), $data)->assertUnprocessable();
        Http::assertNothingSent();
    }
}
