<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Quote;
use App\Models\User;
use Carbon\CarbonImmutable;
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
        $this->withoutVite();
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

    private function datedOffer(string $status, string $createdAt, ?string $updatedAt, array $attributes = []): Quote
    {
        $offer = $this->offer($status, $attributes);
        $offer->timestamps = false;
        $offer->forceFill(['created_at' => $createdAt, 'updated_at' => $updatedAt])->save();
        $offer->timestamps = true;

        return $offer;
    }

    public function test_latest_changes_groups_statuses_and_orders_each_by_update_time_with_a_legacy_fallback(): void
    {
        $older = $this->datedOffer('accepted', '2026-11-01 09:00:00', '2026-11-02 09:00:00');
        $newerCreation = $this->datedOffer('presented', '2026-11-24 09:00:00', '2026-11-24 09:00:00');
        $rejected = $this->datedOffer('rejected', '2026-10-01 09:00:00', '2026-11-25 15:00:00');
        $firstTie = $this->datedOffer('draft', '2026-11-03 09:00:00', '2026-11-25 14:00:00');
        $secondTie = $this->datedOffer('presented', '2026-11-02 09:00:00', '2026-11-25 14:00:00');
        $thirdTie = $this->datedOffer('presented', '2026-11-01 08:00:00', '2026-11-25 14:00:00');
        $legacy = $this->datedOffer('accepted', '2026-11-25 13:00:00', null);
        $deleted = $this->datedOffer('presented', '2026-11-25 16:00:00', '2026-11-25 16:00:00');
        $deleted->delete();
        $before = Quote::all()->mapWithKeys(fn ($offer) => [$offer->id => $offer->getRawOriginal('updated_at')])->all();

        $this->get(route('quotes.index', ['sort' => 'updated']))->assertOk()->assertSee('Ultima modifica')
            ->assertSee('25/11/2026 15:00')->assertViewHas('total', 7)
            ->assertViewHas('quotes', fn ($quotes) => $quotes->pluck('id')->all() === [
                $firstTie->id, $thirdTie->id, $secondTie->id, $newerCreation->id, $legacy->id, $older->id, $rejected->id,
            ]);
        $this->assertSame($before, Quote::all()->mapWithKeys(fn ($offer) => [$offer->id => $offer->getRawOriginal('updated_at')])->all());
        $this->get(route('quotes.index'))->assertOk()->assertViewHas('recentlyUpdated', false)
            ->assertViewHas('quotes', fn ($quotes) => $quotes->pluck('id')->all() === [
                $firstTie->id, $newerCreation->id, $secondTie->id, $thirdTie->id, $legacy->id, $older->id, $rejected->id,
            ]);
        $this->assertSame([$legacy->id, $newerCreation->id, $firstTie->id, $secondTie->id, $older->id, $thirdTie->id, $rejected->id],
            Quote::commercialOrder()->pluck('id')->all());
    }

    public function test_an_item_only_edit_updates_recency_even_when_the_total_does_not_change(): void
    {
        $offer = $this->datedOffer('draft', '2026-11-01 09:00:00', '2026-11-01 09:00:00', ['total' => 100]);
        $offer->items()->create(['name' => 'Voce originale', 'quantity' => 1, 'unit_price' => 100, 'total' => 100, 'sort_order' => 0]);
        $this->datedOffer('draft', '2026-11-24 09:00:00', '2026-11-25 12:00:00');
        $this->travelTo(CarbonImmutable::parse('2026-11-25 14:00:00', 'Europe/Rome'));

        $this->put(route('quotes.update', $offer), ['title' => $offer->title,
            'items' => [['name' => 'Voce aggiornata', 'quantity' => '1', 'unit_price' => '100.00']]])
            ->assertSessionHasNoErrors()->assertRedirect(route('quotes.show', $offer));
        $this->assertSame('2026-11-25 14:00:00', $offer->fresh()->updated_at->format('Y-m-d H:i:s'));
        $this->assertSame('100.00', $offer->fresh()->total);
        $this->get(route('quotes.index', ['sort' => 'updated']))->assertOk()
            ->assertViewHas('quotes', fn ($quotes) => $quotes->first()->is($offer));
    }

    public function test_status_changes_and_new_revisions_advance_recency_and_reading_the_pdf_does_not_change_it(): void
    {
        $offer = $this->datedOffer('draft', '2026-11-01 09:00:00', '2026-11-01 09:00:00', ['total' => 100]);
        $offer->items()->create(['name' => 'Servizio di prova', 'quantity' => 1, 'unit_price' => 100, 'total' => 100, 'sort_order' => 0]);
        $this->datedOffer('presented', '2026-11-20 09:00:00', '2026-11-25 11:00:00');
        $this->travelTo(CarbonImmutable::parse('2026-11-25 12:00:00', 'Europe/Rome'));
        $this->post(route('quotes.present', $offer))->assertRedirect();
        $this->get(route('quotes.index', ['sort' => 'updated']))->assertOk()
            ->assertViewHas('quotes', fn ($quotes) => $quotes->first()->is($offer));
        $this->travelTo(now()->addHour());
        $this->post(route('quotes.reject', $offer))->assertRedirect();
        $this->get(route('quotes.index', ['sort' => 'updated']))->assertOk()
            ->assertViewHas('quotes', fn ($quotes) => $quotes->last()->is($offer) && $quotes->last()->status === 'rejected');
        $this->travelTo(now()->addHour());
        $this->post(route('quotes.revise', $offer))->assertRedirect();
        $revision = $offer->nextQuote()->sole();
        $this->get(route('quotes.index', ['sort' => 'updated']))->assertOk()
            ->assertViewHas('quotes', fn ($quotes) => $quotes->first()->is($revision) && $quotes->first()->revision === 2);
        $updatedAt = $revision->getRawOriginal('updated_at');
        $this->travelTo(now()->addHour());
        $this->get(route('quotes.document', $revision))->assertOk();
        $this->post(route('quotes.revise', $offer))->assertRedirect(route('quotes.edit', $revision));
        $this->assertSame($updatedAt, $revision->fresh()->getRawOriginal('updated_at'));
    }

    public function test_latest_changes_uses_a_single_active_shortcut_without_a_count_and_preserves_search_and_view(): void
    {
        $this->offer();
        $filters = ['q' => 'Restyling', 'client_id' => $this->client->id, 'view' => 'kanban', 'sort' => 'updated'];
        $response = $this->get(route('quotes.index', $filters))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $nav = '//nav[contains(@class,"quote-status-filters")]';
        $recent = $xpath->query($nav.'/a[normalize-space(.)="Ultime modifiche"]')->item(0);
        $this->assertNotNull($recent);
        $this->assertSame(0, $recent->getElementsByTagName('span')->length);
        $this->assertSame('page', $recent->getAttribute('aria-current'));
        $this->assertSame(1, $xpath->query($nav.'/a[@aria-current="page"]')->length);
        $this->assertSame(1, $xpath->query('//form[@role="search"]/input[@name="sort" and @value="updated"]')->length);
        $checks = [
            [$recent, $filters],
            [$xpath->query($nav.'/a[starts-with(normalize-space(.),"Tutte")]')->item(0), array_diff_key($filters, ['sort' => true])],
            [$xpath->query($nav.'/a[starts-with(normalize-space(.),"Presentate")]')->item(0), array_diff_key($filters, ['sort' => true]) + ['status' => 'presented']],
            [$xpath->query('//nav[@aria-label="Visualizzazione offerte"]/a[normalize-space(.)="Lista"]')->item(0), array_replace($filters, ['view' => 'list'])],
            [$xpath->query('//a[normalize-space(.)="Azzera filtri"]')->item(0), ['view' => 'kanban', 'sort' => 'updated']],
        ];
        foreach ($checks as [$link, $expected]) {
            $this->assertNotNull($link);
            parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
            $this->assertEquals($expected, $query);
        }
    }

    public function test_latest_changes_paginates_the_list_and_each_board_column_without_losing_the_order(): void
    {
        $stamp = CarbonImmutable::parse('2026-11-25 10:00:00', 'Europe/Rome');
        $ids = [];
        for ($index = 0; $index < 22; $index++) {
            $ids[] = $this->datedOffer('presented', $stamp->subMinutes($index)->toDateTimeString(), $stamp->addMinutes($index)->toDateTimeString())->id;
        }
        $expected = array_reverse($ids);
        $filters = ['q' => 'Restyling', 'client_id' => $this->client->id, 'view' => 'list', 'sort' => 'updated'];
        $response = $this->get(route('quotes.index', $filters))->assertOk();
        $quotes = $response->viewData('quotes');
        $this->assertSame(array_slice($expected, 0, 20), $quotes->pluck('id')->all());
        parse_str(parse_url($quotes->nextPageUrl(), PHP_URL_QUERY), $next);
        $this->assertEquals($filters + ['page' => 2], $next);
        $this->get($quotes->nextPageUrl())->assertOk()
            ->assertViewHas('quotes', fn ($quotes) => $quotes->pluck('id')->all() === array_slice($expected, 20));
        $response = $this->get(route('quotes.index', array_replace($filters, ['view' => 'kanban'])))->assertOk();
        $presented = $response->viewData('columns')['presented'];
        $this->assertSame(array_slice($expected, 0, 10), $presented->pluck('id')->all());
        parse_str(parse_url($presented->nextPageUrl(), PHP_URL_QUERY), $next);
        $this->assertEquals(array_replace($filters, ['view' => 'kanban']) + ['presented_page' => 2], $next);
        $this->get($presented->nextPageUrl())->assertOk()
            ->assertViewHas('columns', fn ($columns) => $columns['presented']->pluck('id')->all() === array_slice($expected, 10, 10));
    }

    public function test_latest_changes_preserves_commercial_visibility_and_rejects_invalid_sort_values(): void
    {
        $visible = $this->offer('accepted', ['title' => 'Offerta visibile']);
        $this->offer('draft', ['title' => 'Bozza riservata']);
        $other = Client::factory()->create();
        $this->offer('presented', ['client_id' => $other->id, 'title' => 'Offerta riservata']);
        $this->actingAs($this->commercial);
        foreach (['list', 'kanban'] as $view) {
            $this->get(route('quotes.index', ['sort' => 'updated', 'view' => $view]))->assertOk()
                ->assertSee($visible->title)->assertDontSee('Bozza riservata')->assertDontSee('Offerta riservata')
                ->assertViewHas('total', 1)->assertViewHas('statusCounts', fn ($counts) => $counts->all() === ['accepted' => 1]);
        }
        $this->get(route('quotes.index', ['sort' => 'updated', 'client_id' => $other->id]))->assertNotFound();
        foreach (['oldest', ['updated']] as $sort) {
            $this->getJson(route('quotes.index', ['sort' => $sort]))->assertUnprocessable()->assertJsonValidationErrors('sort');
        }
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
