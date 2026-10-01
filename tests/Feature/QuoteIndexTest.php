<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class QuoteIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $commercial;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->commercial = User::factory()->create(['role' => UserRole::Commercial]);
        $this->client = Client::factory()->create(['commercial_user_id' => $this->commercial->id, 'name' => 'Cliente Aurora', 'company_name' => 'Studio Boreale']);
        $this->actingAs($this->admin);
    }

    private function offer(string $status = 'presented', array $attributes = []): Quote
    {
        return Quote::create($attributes + ['client_id' => $this->client->id, 'created_by' => $this->admin->id,
            'title' => 'Restyling dimostrativo', 'status' => $status, 'total' => 300]);
    }

    public function test_search_matches_offer_client_company_and_reference(): void
    {
        $offer = $this->offer();
        $this->offer('presented', ['client_id' => Client::factory()->create()->id, 'title' => 'Servizio diverso']);
        foreach (['  Restyling  ', 'Aurora', 'Boreale', '#'.$offer->id] as $search) {
            $this->get(route('quotes.index', ['q' => $search]))->assertOk()
                ->assertViewHas('quotes', fn ($quotes) => $quotes->pluck('id')->all() === [$offer->id])
                ->assertViewHas('total', 1);
        }
        $this->offer('accepted', ['title' => 'Proposta 0']);
        $this->get(route('quotes.index', ['q' => '0']))->assertOk()->assertViewHas('total', 1);
    }

    public function test_search_client_and_status_combine_and_counts_respect_search(): void
    {
        $this->offer('draft');
        $presented = $this->offer();
        $this->offer('accepted', ['title' => 'Fotografie dimostrative']);
        $this->offer('presented', ['client_id' => Client::factory()->create()->id]);
        $deleted = $this->offer();
        $deleted->delete();
        foreach (['list', 'kanban'] as $view) {
            $response = $this->get(route('quotes.index', ['q' => 'Restyling', 'client_id' => $this->client->id, 'status' => 'presented', 'view' => $view]));
            $response->assertOk()->assertViewHas('total', 1)
                ->assertViewHas('statusCounts', fn ($counts) => $counts->all() === ['draft' => 1, 'presented' => 1])
                ->assertSee($presented->title)->assertDontSee('Fotografie dimostrative')
                ->assertViewHas('selectedClient', fn ($client) => $client->is($this->client));
            if ($view === 'kanban') {
                $response->assertViewHas('columns', fn ($columns) => array_keys($columns) === ['presented'] && $columns['presented']->pluck('id')->all() === [$presented->id]);
            } else {
                $response->assertViewHas('quotes', fn ($quotes) => $quotes->pluck('id')->all() === [$presented->id]);
            }
        }
    }

    public function test_commercial_search_and_counts_do_not_expose_drafts_or_other_clients(): void
    {
        $visible = $this->offer('presented', ['title' => 'Documento visibile']);
        $this->offer('draft', ['title' => 'Bozza riservata']);
        $otherClient = Client::factory()->create(['name' => 'Cliente Aurora esterno']);
        $this->offer('accepted', ['client_id' => $otherClient->id, 'title' => 'Documento riservato']);
        $this->actingAs($this->commercial);
        foreach (['list', 'kanban'] as $view) {
            $this->get(route('quotes.index', ['q' => 'Aurora', 'view' => $view]))->assertOk()
                ->assertSee($visible->title)->assertDontSee('Bozza riservata')->assertDontSee('Documento riservato')
                ->assertDontSee('Nuova offerta')->assertDontSee('Modifica Documento visibile')
                ->assertViewHas('statusCounts', fn ($counts) => $counts->all() === ['presented' => 1])
                ->assertViewHas('statuses', fn ($statuses) => ! isset($statuses['draft']))
                ->assertViewHas('total', 1);
            $this->get(route('quotes.index', ['q' => 'Aurora', 'status' => 'draft', 'view' => $view]))->assertOk()->assertViewHas('total', 0);
        }
        $this->get(route('quotes.index', ['client_id' => $otherClient->id]))->assertNotFound();
    }

    public function test_kanban_paginates_each_status_independently_and_preserves_filters(): void
    {
        foreach (['draft' => 12, 'presented' => 11, 'accepted' => 1, 'rejected' => 1] as $status => $count) {
            for ($index = 0; $index < $count; $index++) {
                $this->offer($status, ['title' => 'Servizio '.$status.' '.$index]);
            }
        }
        $filters = ['view' => 'kanban', 'q' => 'Servizio', 'client_id' => $this->client->id];
        $this->get(route('quotes.index', $filters))->assertOk()->assertViewHas('total', 25)
            ->assertViewHas('columns', function ($columns) use ($filters) {
                $this->assertSame(['draft', 'presented', 'accepted', 'rejected'], array_keys($columns));
                $this->assertCount(10, $columns['draft']);
                $this->assertCount(10, $columns['presented']);
                $this->assertCount(1, $columns['accepted']);
                $this->assertCount(1, $columns['rejected']);
                parse_str(parse_url($columns['draft']->nextPageUrl(), PHP_URL_QUERY), $query);
                $this->assertEquals($filters + ['draft_page' => 2], $query);

                return true;
            });
        $this->get(route('quotes.index', $filters + ['draft_page' => 2, 'presented_page' => 2]))->assertOk()
            ->assertViewHas('columns', fn ($columns) => $columns['draft']->count() === 2 && $columns['presented']->count() === 1 && $columns['accepted']->count() === 1);
        $this->get(route('quotes.index', $filters + ['draft_page' => 999]))->assertOk()
            ->assertViewHas('columns', fn ($columns) => $columns['draft']->currentPage() === 2 && $columns['draft']->count() === 2);
    }

    public function test_empty_search_keeps_filters_and_view_and_can_be_reset(): void
    {
        $this->offer();
        $filters = ['q' => 'Nessuna corrispondenza', 'status' => 'rejected', 'client_id' => $this->client->id, 'view' => 'kanban'];
        $response = $this->get(route('quotes.index', $filters))->assertOk()->assertViewHas('total', 0)
            ->assertSee('Nessuna offerta trovata con questi filtri.')
            ->assertSee(route('quotes.index', ['view' => 'kanban']));
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $listLink = (new \DOMXPath($document))->query('//nav[@aria-label="Visualizzazione offerte"]/a[normalize-space(.)="Lista"]')->item(0);
        $this->assertNotNull($listLink);
        parse_str(parse_url($listLink->getAttribute('href'), PHP_URL_QUERY), $query);
        $this->assertEquals(array_replace($filters, ['view' => 'list']), $query);
    }

    public function test_invalid_filter_shapes_and_values_are_rejected(): void
    {
        foreach (['q' => ['invalid'], 'client_id' => -1, 'status' => 'unknown', 'view' => ['list'], 'draft_page' => 0] as $field => $value) {
            $this->getJson(route('quotes.index', [$field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }
}
