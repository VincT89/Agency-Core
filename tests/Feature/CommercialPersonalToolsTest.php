<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Dashboard\UserDailyNotes;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Models\UserDailyNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class CommercialPersonalToolsTest extends TestCase
{
    use RefreshDatabase;

    private User $commercial;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        Notification::fake();
        $this->commercial = User::factory()->create(['role' => UserRole::Commercial, 'status' => 'active', 'password_changed_at' => now()]);
        $this->other = User::factory()->create(['role' => UserRole::Admin, 'status' => 'active', 'password_changed_at' => now()]);
        $this->actingAs($this->commercial);
    }

    private function payload(array $attributes = []): array
    {
        return array_merge([
            'title' => 'Appuntamento dimostrativo', 'type' => 'personal', 'status' => 'scheduled',
            'start_at' => '2026-09-10T10:00', 'end_at' => '2026-09-10T11:00',
        ], $attributes);
    }

    public function test_commercial_can_create_edit_reschedule_and_delete_a_personal_appointment(): void
    {
        $this->get(route('calendar-events.create'))->assertOk()->assertSee('Personale')
            ->assertDontSee('name="assigned_to"', false)->assertDontSee('name="client_id"', false)
            ->assertDontSee('name="project_id"', false)->assertDontSee($this->other->name);
        $this->post(route('calendar-events.store'), $this->payload(['created_by' => $this->other->id]))
            ->assertSessionHasNoErrors()->assertRedirect();
        $event = CalendarEvent::query()->sole();
        $this->assertSame($this->commercial->id, $event->created_by);
        $this->assertSame($this->commercial->id, $event->assigned_to);
        $this->assertNull($event->client_id);
        $this->assertNull($event->project_id);
        $this->get(route('calendar-events.show', $event))->assertOk()->assertSee($event->title);
        $this->get(route('calendar-events.edit', $event))->assertOk()->assertDontSee('name="assigned_to"', false);
        $this->put(route('calendar-events.update', $event), $this->payload(['title' => 'Appuntamento aggiornato']))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->patchJson(route('calendar-events.update-date', $event), ['start_at' => '2026-09-11T10:00', 'end_at' => '2026-09-11T11:00'])->assertOk();
        $this->assertSame('Appuntamento aggiornato', $event->fresh()->title);
        $this->assertSame('2026-09-11', $event->fresh()->start_at->toDateString());
        $this->delete(route('calendar-events.destroy', $event))->assertRedirect(route('calendar-events.index'));
        $this->assertDatabaseMissing('calendar_events', ['id' => $event->id]);
    }

    public function test_personal_calendar_cannot_be_used_to_assign_others_or_access_project_events(): void
    {
        $client = Client::factory()->create();
        $project = Project::factory()->create(['client_id' => $client->id]);
        $project->users()->attach($this->commercial->id, ['role' => 'member']);
        $event = CalendarEvent::create($this->payload(['created_by' => $this->commercial->id, 'assigned_to' => $this->commercial->id]));

        foreach (['client_id' => $client->id, 'project_id' => $project->id, 'assigned_to' => $this->other->id, 'type' => 'client_meeting'] as $field => $value) {
            $this->post(route('calendar-events.store'), $this->payload([$field => $value]))->assertSessionHasErrors($field);
            $this->put(route('calendar-events.update', $event), $this->payload([$field => $value]))->assertSessionHasErrors($field);
        }
        $this->assertDatabaseCount('calendar_events', 1);
        $this->assertSame('personal', $event->fresh()->type);
    }

    public function test_list_json_and_direct_access_only_allow_own_personal_appointments(): void
    {
        $client = Client::factory()->create();
        $project = Project::factory()->create(['client_id' => $client->id]);
        $project->users()->attach($this->commercial->id, ['role' => 'member']);
        $own = CalendarEvent::create($this->payload(['created_by' => $this->commercial->id, 'assigned_to' => $this->commercial->id]));
        $foreign = CalendarEvent::create($this->payload(['title' => 'Privato altrui', 'created_by' => $this->other->id, 'assigned_to' => $this->other->id]));
        $projectEvent = CalendarEvent::create($this->payload([
            'title' => 'Riunione di progetto riservata', 'type' => 'client_meeting', 'project_id' => $project->id,
            'created_by' => $this->other->id, 'assigned_to' => $this->commercial->id,
        ]));
        $this->get(route('calendar-events.index'))->assertOk()->assertDontSee('Tutti gli Eventi')
            ->assertViewHas('calendarEvents', fn ($events) => $events->total() === 1 && $events->first()->is($own));
        $this->getJson(route('calendar-events.index', ['format' => 'json', 'department' => 'admin', 'scope' => 'all']))
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $own->id)->assertDontSee($foreign->title)->assertDontSee($projectEvent->title);
        foreach ([$foreign, $projectEvent] as $hidden) {
            $this->assertFalse(Gate::allows('view', $hidden));
            $this->assertFalse(Gate::allows('update', $hidden));
            $this->get(route('calendar-events.show', $hidden))->assertNotFound();
            $this->get(route('calendar-events.edit', $hidden))->assertNotFound();
            $this->put(route('calendar-events.update', $hidden), $this->payload())->assertNotFound();
            $this->patchJson(route('calendar-events.update-date', $hidden), ['start_at' => '2026-09-12T10:00'])->assertNotFound();
            $this->delete(route('calendar-events.destroy', $hidden))->assertNotFound();
        }
    }

    public function test_daily_notes_and_checklists_remain_private_and_editable_for_their_owner(): void
    {
        $foreignNote = UserDailyNote::create(['user_id' => $this->other->id, 'date' => today()]);
        $foreignEntry = $foreignNote->entries()->create(['content' => 'Appunto riservato altrui', 'sort_order' => 1]);
        $foreignItem = $foreignEntry->checklistItems()->create(['label' => 'Promemoria altrui', 'is_completed' => false, 'sort_order' => 1]);
        $this->get(route('daily-notes.index'))->assertOk()->assertSee('Il mio Blocco Note')->assertDontSee($foreignEntry->content);

        $component = Livewire::test(UserDailyNotes::class)->call('addEntry')->assertHasNoErrors();
        $ownEntry = UserDailyNote::where('user_id', $this->commercial->id)->sole()->entries()->sole();
        $component->set('entryContents.'.$ownEntry->id, 'Richiamare il cliente dimostrativo')
            ->set('newChecklistLabels.'.$ownEntry->id, 'Preparare la richiesta')
            ->call('addChecklistItem', $ownEntry->id)->assertSee('Preparare la richiesta');
        $ownItem = $ownEntry->checklistItems()->sole();
        $component->call('toggleChecklistItem', $ownItem->id);
        $this->assertTrue($ownItem->fresh()->is_completed);
        $this->assertSame('Richiamare il cliente dimostrativo', $ownEntry->fresh()->content);
        $component->call('nextDay')->call('previousDay')->assertSee('Preparare la richiesta')
            ->call('addEntry');
        $this->assertSame(1, UserDailyNote::where('user_id', $this->commercial->id)->count());
        $this->assertSame(2, $ownEntry->userDailyNote->entries()->count());

        $component->set('entryContents.'.$foreignEntry->id, 'Modifica non autorizzata')
            ->call('deleteEntry', $foreignEntry->id)->call('toggleChecklistItem', $foreignItem->id)
            ->call('deleteChecklistItem', $foreignItem->id);
        $this->assertSame('Appunto riservato altrui', $foreignEntry->fresh()->content);
        $this->assertFalse($foreignItem->fresh()->is_completed);
        $component->call('deleteChecklistItem', $ownItem->id)->call('deleteEntry', $ownEntry->id);
        $this->assertDatabaseMissing('user_daily_note_entries', ['id' => $ownEntry->id]);
    }
}
