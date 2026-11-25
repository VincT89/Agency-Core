<?php

namespace Tests\Feature;

use App\Enums\Social\CommentSource;
use App\Enums\UserRole;
use App\Livewire\Tasks\TaskComments;
use App\Livewire\Tickets\TicketComments;
use App\Models\Chatbot\ChatbotClient;
use App\Models\Chatbot\ChatbotTicket;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\Concerns\SignsN8nRequests;
use Tests\TestCase;

class WorkItemRecentActivityTest extends TestCase
{
    use RefreshDatabase;
    use SignsN8nRequests;

    private User $admin;
    private Client $client;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Bus::fake();
        Notification::fake();
        $this->travelTo(now()->setDate(2026, 11, 25)->setTime(14, 0));
        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'status' => 'active']);
        $this->actingAs($this->admin);
        $this->client = Client::factory()->create(['name' => 'Cliente di prova']);
        $this->project = Project::factory()->create(['client_id' => $this->client->id, 'status' => 'active']);
    }

    private function item(string $type, array $attributes = [], ?string $created = '2026-11-01 09:00:00', ?string $updated = '2026-11-01 09:00:00'): Task|Ticket
    {
        $common = ['project_id' => $this->project->id, 'created_by' => $this->admin->id,
            'assigned_to' => $this->admin->id, 'priority' => 'medium', 'title' => ucfirst($type).' di prova'];
        $item = $type === 'tasks'
            ? Task::factory()->create($attributes + $common + ['status' => 'todo'])
            : Ticket::factory()->create($attributes + $common + ['client_id' => $this->client->id, 'status' => 'open', 'type' => 'request', 'opened_at' => $created]);
        DB::table($item->getTable())->where('id', $item->id)->update(['created_at' => $created, 'updated_at' => $updated]);

        return $item->fresh();
    }

    private function assertItems(string $type, array $expected, array $filters = []): TestResponse
    {
        $response = $this->get(route($type.'.index', $filters))->assertOk();
        $key = $type === 'tickets' ? 'tickets' : (($filters['view'] ?? null) === 'kanban' ? 'kanbanTasks' : 'taskList');
        $response->assertViewHas($key, fn ($items) => $items->pluck('id')->all() === array_map(fn ($item) => $item->id, $expected));

        return $response;
    }

    private function linkQuery(TestResponse $response, string $label): array
    {
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(), LIBXML_NONET);
        $links = (new \DOMXPath($document))->query('//a[normalize-space(.)="'.$label.'"]');
        $this->assertSame(1, $links->length, $label);
        $link = $links->item(0);
        if ($label === 'Ultime modifiche') {
            $this->assertSame(0, $link->getElementsByTagName('span')->length);
            $this->assertSame('page', $link->getAttribute('aria-current'));
        }
        parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY) ?? '', $query);

        return $query;
    }

    public function test_tasks_group_states_then_sort_by_creation_or_latest_activity_in_list_and_board(): void
    {
        $older = $this->item('tasks', [], '2026-11-01 09:00:00', '2026-11-25 12:00:00');
        $newer = $this->item('tasks', [], '2026-11-24 09:00:00', '2026-11-24 09:00:00');
        $done = $this->item('tasks', ['status' => 'done'], '2026-11-25 13:00:00', '2026-11-25 13:00:00');
        $waiting = $this->item('tasks', ['status' => 'waiting']);
        $progress = $this->item('tasks', ['status' => 'in_progress']);
        $review = $this->item('tasks', ['status' => 'review']);
        $cancelled = $this->item('tasks', ['status' => 'cancelled']);

        $this->assertItems('tasks', [$newer, $older, $progress, $waiting, $review, $done, $cancelled]);
        foreach (['list', 'kanban'] as $view) {
            $this->assertItems('tasks', [$older, $newer, $progress, $waiting, $review, $done, $cancelled], ['sort' => 'updated', 'view' => $view])
                ->assertSee('Ultima modifica: 25/11/2026 12:00');
        }
    }

    public function test_tickets_group_open_states_before_resolved_and_closed_even_when_those_are_newer(): void
    {
        $older = $this->item('tickets', [], '2026-11-01 09:00:00', '2026-11-25 12:00:00');
        $newer = $this->item('tickets', [], '2026-11-24 09:00:00', '2026-11-24 09:00:00');
        $closed = $this->item('tickets', ['status' => 'closed'], '2026-11-25 13:00:00', '2026-11-25 13:00:00');
        $resolved = $this->item('tickets', ['status' => 'resolved']);
        $waiting = $this->item('tickets', ['status' => 'waiting']);
        $progress = $this->item('tickets', ['status' => 'in_progress']);

        $this->assertItems('tickets', [$newer, $older, $progress, $waiting, $resolved, $closed]);
        $this->assertItems('tickets', [$older, $newer, $progress, $waiting, $resolved, $closed], ['sort' => 'updated'])
            ->assertSee('Ultima modifica: 25/11/2026 12:00');
    }

    public function test_existing_comments_legacy_dates_ties_and_read_only_browsing_are_handled(): void
    {
        foreach (['tasks', 'tickets'] as $type) {
            $record = $this->item($type, [], '2026-11-01 09:00:00', '2026-11-24 09:00:00');
            $oldComment = $record->comments()->create(['user_id' => $this->admin->id, 'body' => 'Vecchio commento di prova', 'source' => CommentSource::Internal]);
            DB::table($oldComment->getTable())->where('id', $oldComment->id)->update(['created_at' => '2026-11-02 09:00:00']);
            $commented = $this->item($type);
            foreach (['2026-11-22 10:00:00', '2026-11-25 12:00:00'] as $date) {
                $comment = $commented->comments()->create(['user_id' => $this->admin->id, 'body' => 'Commento di prova', 'source' => CommentSource::Internal]);
                DB::table($comment->getTable())->where('id', $comment->id)->update(['created_at' => $date]);
            }
            $legacy = $this->item($type, [], '2026-11-25 11:00:00', null);
            $tie = $this->item($type, [], '2026-11-01 09:00:00', '2026-11-25 11:00:00');
            $undated = $this->item($type, [], null, null);
            $before = DB::table($record->getTable())->get(['id', 'created_at', 'updated_at'])->toJson();

            $response = $this->assertItems($type, [$commented, $tie, $legacy, $record, $undated], ['sort' => 'updated'])
                ->assertSee('25/11/2026 12:00')->assertSee('Non disponibile')->assertDontSee('Commento di prova');
            $items = $response->viewData($type === 'tasks' ? 'taskList' : 'tickets');
            $this->assertSame('2026-11-25 12:00:00', $items->first()->last_activity_at->format('Y-m-d H:i:s'));
            $this->assertFalse($items->first()->relationLoaded('comments'));
            $this->assertSame($before, DB::table($record->getTable())->get(['id', 'created_at', 'updated_at'])->toJson());
        }
    }

    public function test_new_task_comment_changes_recent_order_without_changing_record_or_default_creation_order(): void
    {
        $older = $this->item('tasks');
        $newer = $this->item('tasks', [], '2026-11-24 09:00:00', '2026-11-24 09:00:00');
        $this->assertItems('tasks', [$newer, $older], ['sort' => 'updated']);
        Livewire::test(TaskComments::class, ['task' => $older])
            ->set('body', 'Commento operativo di prova')->call('addComment')->assertHasNoErrors();
        $this->assertItems('tasks', [$older, $newer], ['sort' => 'updated']);
        $this->assertItems('tasks', [$newer, $older]);
        $this->assertSame('2026-11-01 09:00:00', $older->fresh()->getRawOriginal('updated_at'));
    }

    public function test_ticket_comments_from_operator_and_commercial_count_as_activity(): void
    {
        $commercial = User::factory()->create(['role' => UserRole::Commercial]);
        $older = $this->item('tickets', ['created_by' => $commercial->id]);
        $newer = $this->item('tickets', ['created_by' => $commercial->id], '2026-11-24 09:00:00', '2026-11-24 09:00:00');
        Livewire::test(TicketComments::class, ['ticket' => $older])
            ->set('body', 'Risposta operatore di prova')->call('addComment')->assertHasNoErrors();
        $this->assertItems('tickets', [$older, $newer], ['sort' => 'updated']);
        $this->travel(1)->minutes();
        $this->actingAs($commercial);
        Livewire::test(TicketComments::class, ['ticket' => $newer])
            ->set('body', 'Risposta commerciale di prova')->call('addComment')->assertHasNoErrors();
        $this->get(route('tickets.index', ['sort' => 'updated']))->assertOk()->assertViewIs('tickets.commercial.index')
            ->assertViewHas('tickets', fn ($items) => $items->pluck('id')->all() === [$newer->id, $older->id])
            ->assertSee('25/11/2026 14:01');
    }

    public function test_real_status_and_content_updates_are_included(): void
    {
        foreach (['tasks', 'tickets'] as $type) {
            $older = $this->item($type);
            $newer = $this->item($type, [], '2026-11-24 09:00:00', '2026-11-24 09:00:00');
            $this->patch(route($type.'.update-status', $older), ['status' => 'waiting'])->assertSessionHasNoErrors()->assertRedirect();
            $this->assertSame(now()->format('Y-m-d H:i:s'), $older->fresh()->getRawOriginal('updated_at'));
            $this->assertItems($type, [$older], ['sort' => 'updated', 'status' => 'waiting']);
            $this->travel(1)->minutes();
            $this->put(route($type.'.update', $newer), ['project_id' => $this->project->id, 'client_id' => $this->client->id,
                'title' => 'Titolo aggiornato di prova', 'description' => 'Contenuto aggiornato di prova', 'priority' => 'high',
                'assigned_to' => $this->admin->id, 'status' => 'waiting', 'type' => 'request'])
                ->assertSessionHasNoErrors()->assertRedirect();
            $this->assertItems($type, [$newer, $older], ['sort' => 'updated', 'status' => 'waiting']);
        }
    }

    public function test_incoming_client_reply_counts_but_delivery_callback_does_not(): void
    {
        $ticket = $this->item('tickets');
        $newer = $this->item('tickets', [], '2026-11-24 09:00:00', '2026-11-24 09:00:00');
        $chatbotClient = ChatbotClient::create(['client_id' => $this->client->id, 'name' => 'Cliente di prova']);
        ChatbotTicket::create(['chatbot_client_id' => $chatbotClient->id, 'client_id' => $this->client->id,
            'ticket_id' => $ticket->id, 'code' => $ticket->code, 'title' => $ticket->title, 'status' => 'open']);
        $headers = ['Authorization' => 'Bearer '.$this->n8nToken];
        $this->signedN8nJson('POST', '/api/v1/integrations/n8n/chatbot/client-message', [
            'client_id' => $this->client->id, 'session_type' => 'ticket', 'session_id' => $ticket->id,
            'message' => 'Risposta cliente di prova', 'type' => 'comment',
        ], $headers)->assertOk();
        $this->assertItems('tickets', [$ticket, $newer], ['sort' => 'updated']);

        $comment = $ticket->comments()->sole();
        $comment->update(['delivery_channel' => 'sody', 'delivery_status' => 'pending']);
        $this->travel(1)->minutes();
        $newer->touch();
        $this->travel(1)->minutes();
        $this->signedN8nJson('POST', '/api/v1/integrations/n8n/chatbot/outgoing-messages/ticket_comment_'.$comment->id.'/status', [
            'status' => 'sent', 'external_message_id' => 'messaggio-di-prova',
        ], $headers)->assertOk();
        $this->assertSame('sent', $comment->fresh()->delivery_status);
        $this->assertItems('tickets', [$newer, $ticket], ['sort' => 'updated']);
    }

    public function test_links_keep_filters_and_view_reset_pages_and_do_not_add_a_counter(): void
    {
        $this->item('tasks');
        $response = $this->get(route('tasks.index', ['sort' => 'updated', 'status' => 'waiting', 'page' => 2,
            'view' => 'kanban', 'project_id' => $this->project->id, 'assigned_to' => $this->admin->id, 'priority' => 'high']))->assertOk();
        $base = ['view' => 'kanban', 'project_id' => (string) $this->project->id, 'assigned_to' => (string) $this->admin->id, 'priority' => 'high'];
        $this->assertEquals($base + ['sort' => 'updated'], $this->linkQuery($response, 'Ultime modifiche'));
        $this->assertEquals($base, $this->linkQuery($response, 'Tutti'));
        $this->assertEquals($base + ['status' => 'todo'], $this->linkQuery($response, 'Da fare'));
        $this->assertEquals(['view' => 'kanban', 'sort' => 'updated'], $this->linkQuery($response, 'Reset'));
        $response->assertSee('name="sort" value="updated"', false);

        $response = $this->get(route('tickets.index', ['sort' => 'updated', 'status' => 'waiting', 'page' => 2, 'search' => 'prova']))->assertOk();
        $this->assertEquals(['search' => 'prova', 'sort' => 'updated'], $this->linkQuery($response, 'Ultime modifiche'));
        $this->assertEquals(['search' => 'prova'], $this->linkQuery($response, 'Tutti'));
        $this->assertEquals(['search' => 'prova', 'status' => 'open'], $this->linkQuery($response, 'Aperti'));
        $this->assertEquals(['sort' => 'updated'], $this->linkQuery($response, 'Reset'));
        $response->assertSee('name="sort" value="updated"', false);
    }

    public function test_pagination_is_stable_and_filters_apply_in_the_database(): void
    {
        foreach (['tasks' => 20, 'tickets' => 15] as $type => $pageSize) {
            $items = [];
            for ($i = 0; $i < $pageSize + 2; $i++) {
                $items[] = $this->item($type, ['title' => 'Filtro di prova', 'priority' => 'high']);
            }
            $other = $this->item($type, ['title' => 'Esclusa', 'priority' => 'low']);
            $items = array_reverse($items);
            $filters = $type === 'tasks'
                ? ['project_id' => $this->project->id, 'assigned_to' => $this->admin->id, 'priority' => 'high']
                : ['search' => 'Filtro di prova'];
            $response = $this->assertItems($type, array_slice($items, 0, $pageSize), $filters + ['sort' => 'updated'])->assertDontSee($other->title);
            $this->assertEquals($filters + ['sort' => 'updated', 'page' => '2'], $this->linkQuery($response, '2'));
            $this->assertItems($type, array_slice($items, $pageSize), $filters + ['sort' => 'updated', 'page' => 2]);
        }
    }

    public function test_project_scope_is_preserved_for_staff(): void
    {
        $developer = User::factory()->create(['role' => UserRole::Developer]);
        $this->project->users()->attach($developer->id, ['role' => 'developer', 'assignment_status' => 'active', 'assigned_at' => now()]);
        $otherProject = Project::factory()->create();
        foreach (['tasks', 'tickets'] as $type) {
            $visible = $this->item($type, ['title' => 'Attività visibile di prova']);
            $hidden = $this->item($type, ['project_id' => $otherProject->id, 'title' => 'Attività riservata di prova']);
            $this->actingAs($developer);
            $this->assertItems($type, [$visible], ['sort' => 'updated'])->assertDontSee($hidden->title);
            $this->get(route($type.'.show', $hidden))->assertNotFound();
            $this->actingAs($this->admin);
        }
    }

    public function test_commercial_views_preserve_visibility_and_do_not_reveal_internal_task_comment_activity(): void
    {
        $commercial = User::factory()->create(['role' => UserRole::Commercial]);
        $this->client->update(['commercial_user_id' => $commercial->id]);
        $oldTask = $this->item('tasks');
        $newTask = $this->item('tasks', [], '2026-11-24 09:00:00', '2026-11-24 09:00:00');
        $oldTask->comments()->create(['user_id' => $this->admin->id, 'body' => 'Commento interno riservato di prova', 'source' => CommentSource::Internal]);
        $hiddenTask = $this->item('tasks', ['project_id' => Project::factory()->create()->id, 'title' => 'Task riservata di prova']);
        $ownTicket = $this->item('tickets', ['created_by' => $commercial->id]);
        $hiddenTicket = $this->item('tickets', ['title' => 'Ticket riservato di prova']);

        $this->actingAs($commercial);
        $response = $this->get(route('tasks.index', ['sort' => 'updated', 'client_id' => $this->client->id]))->assertOk()
            ->assertViewIs('tasks.commercial.index')->assertViewHas('tasks', fn ($items) => $items->pluck('id')->all() === [$newTask->id, $oldTask->id])
            ->assertSee('24/11/2026 09:00')->assertDontSee('25/11/2026 14:00')->assertDontSee('Commento interno riservato di prova')->assertDontSee($hiddenTask->title);
        $this->assertEquals(['sort' => 'updated', 'client_id' => (string) $this->client->id], $this->linkQuery($response, 'Ultime modifiche'));
        $response = $this->get(route('tickets.index', ['sort' => 'updated']))->assertOk()->assertViewIs('tickets.commercial.index')
            ->assertViewHas('tickets', fn ($items) => $items->pluck('id')->all() === [$ownTicket->id])->assertDontSee($hiddenTicket->title);
        $this->assertEquals(['sort' => 'updated'], $this->linkQuery($response, 'Ultime modifiche'));
    }

    public function test_editing_only_requested_services_updates_ticket_activity(): void
    {
        $ticket = $this->item('tickets', ['type' => 'quote']);
        $newer = $this->item('tickets', [], '2026-11-24 09:00:00', '2026-11-24 09:00:00');
        $ticket->requestedServices()->create(['name' => 'Servizio iniziale di prova', 'sort_order' => 0]);
        $this->put(route('tickets.update', $ticket), [
            'project_id' => $this->project->id, 'client_id' => $this->client->id, 'title' => $ticket->title,
            'type' => 'quote', 'status' => 'open', 'priority' => 'medium', 'assigned_to' => $this->admin->id,
            'requested_services' => [['name' => 'Servizio modificato di prova']],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertItems('tickets', [$ticket, $newer], ['sort' => 'updated']);
        $this->assertSame(now()->format('Y-m-d H:i:s'), $ticket->fresh()->getRawOriginal('updated_at'));
    }

    public function test_invalid_or_structured_sort_input_is_rejected(): void
    {
        foreach (['tasks', 'tickets'] as $type) {
            foreach (['unexpected', ['updated'], 'updated desc; drop table users'] as $sort) {
                $this->getJson(route($type.'.index', ['sort' => $sort]))->assertUnprocessable()->assertJsonValidationErrors('sort');
            }
        }
    }
}
