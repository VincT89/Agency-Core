<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Shared\AttachmentManager;
use App\Models\Attachment;
use App\Models\Client;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class QuoteManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $commercial;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        Storage::fake('attachments');
        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->commercial = User::factory()->create(['role' => UserRole::Commercial]);
        $this->client = Client::factory()->create(['commercial_user_id' => $this->commercial->id]);
        $this->actingAs($this->admin);
    }

    private function quote(string $status = 'draft', array $extra = []): Quote
    {
        $quote = Quote::create($extra + ['client_id' => $this->client->id, 'created_by' => $this->admin->id,
            'title' => 'Offerta '.$status, 'status' => $status, 'total' => '10.00']);
        $quote->items()->create(['name' => 'Servizio', 'quantity' => '1', 'unit_price' => '10', 'total' => '10', 'sort_order' => 0]);

        return $quote;
    }

    private function attachment(Quote $quote): Attachment
    {
        Storage::disk('attachments')->put('quote/'.$quote->id.'/test.txt', 'Allegato di prova');

        return $quote->attachments()->create(['type' => 'document', 'uploaded_by' => $this->admin->id, 'disk' => 'attachments',
            'directory' => 'quote/'.$quote->id, 'path' => 'quote/'.$quote->id.'/test.txt', 'original_name' => 'test.txt', 'stored_name' => 'test.txt',
            'mime_type' => 'text/plain', 'extension' => 'txt', 'size' => 17]);
    }

    public function test_rejected_quotes_are_last_in_offer_client_and_ticket_history(): void
    {
        $ticket = Ticket::factory()->create(['client_id' => $this->client->id, 'type' => 'quote', 'title' => 'Richiesta dimostrativa', 'created_by' => $this->admin->id]);
        $active = $this->quote('presented', ['title' => 'Offerta attiva meno recente', 'ticket_id' => $ticket->id, 'created_at' => now()->subDays(5)]);
        $rejected = $this->quote('rejected', ['title' => 'Offerta rifiutata recente', 'ticket_id' => $ticket->id]);
        foreach ([route('quotes.index'), route('clients.show', $this->client), route('tickets.show', $ticket)] as $url) {
            $this->get($url)->assertOk()->assertSeeInOrder([$active->title, $rejected->title])->assertSee('commercial-history-row is-rejected', false)->assertSee('Rifiutata');
        }
        $this->actingAs($this->commercial)->get(route('quotes.index'))->assertOk()->assertSeeInOrder([$active->title, $rejected->title]);
        $this->get(route('quotes.index', ['status' => 'rejected']))->assertOk()->assertSee($rejected->title)->assertDontSee($active->title);
    }

    public function test_rejected_ordering_is_applied_before_pagination(): void
    {
        $rejected = $this->quote('rejected', ['title' => 'Rifiutata da mostrare dopo tutte']);
        for ($i = 0; $i < 21; $i++) {
            $this->quote('presented', ['created_at' => now()->subDay()]);
        }
        $this->get(route('quotes.index'))->assertOk()->assertDontSee($rejected->title);
        $this->get(route('quotes.index', ['page' => 2]))->assertOk()->assertSee($rejected->title);
    }

    public function test_finance_roles_delete_quotes_without_removing_linked_projects_or_files(): void
    {
        foreach ([UserRole::Admin, UserRole::Administration] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $project = Project::factory()->create(['client_id' => $this->client->id]);
            $quote = $this->quote('accepted', ['project_id' => $project->id]);
            $attachment = $this->attachment($quote);
            $this->delete(route('quotes.destroy', $quote))->assertRedirect(route('quotes.index'));
            $this->assertSoftDeleted('quotes', ['id' => $quote->id]);
            $this->assertDatabaseHas('projects', ['id' => $project->id]);
            $this->assertDatabaseHas('quote_items', ['quote_id' => $quote->id]);
            Storage::disk('attachments')->assertExists($attachment->path);
            $this->get(route('quotes.show', $quote))->assertNotFound();
            $this->get(route('attachments.download', $attachment))->assertForbidden();
            $this->delete(route('attachments.destroy', $attachment))->assertForbidden();
        }
    }

    public function test_deleted_offers_disappear_from_all_lists(): void
    {
        $ticket = Ticket::factory()->create(['client_id' => $this->client->id, 'type' => 'quote', 'title' => 'Richiesta dimostrativa', 'created_by' => $this->admin->id]);
        $quote = $this->quote('rejected', ['ticket_id' => $ticket->id, 'title' => 'Offerta rimossa dalle liste']);
        $this->delete(route('quotes.destroy', $quote))->assertRedirect();
        foreach ([route('quotes.index'), route('clients.show', $this->client), route('tickets.show', $ticket)] as $url) {
            $this->get($url)->assertOk()->assertDontSee($quote->title);
        }
    }

    public function test_commercial_and_other_roles_cannot_delete_or_upload_to_an_offer(): void
    {
        $quote = $this->quote('presented');
        foreach ([UserRole::Commercial, UserRole::Marketing, UserRole::Photographer, UserRole::Developer] as $role) {
            $this->actingAs($role === UserRole::Commercial ? $this->commercial : User::factory()->create(['role' => $role]));
            $this->delete(route('quotes.destroy', $quote))->assertForbidden();
            $this->post(route('attachments.store'), ['attachable_type' => 'quote', 'attachable_id' => $quote->id, 'type' => 'document', 'file' => UploadedFile::fake()->createWithContent('test.txt', 'Documento')])->assertForbidden();
        }
        $this->actingAs($this->commercial);
        Livewire::test(AttachmentManager::class, ['model' => $quote])->call('upload')->assertForbidden();
        $this->assertNotSoftDeleted('quotes', ['id' => $quote->id]);
    }

    public function test_admin_and_administration_can_upload_and_delete_offer_attachments(): void
    {
        $quote = $this->quote('accepted');
        $this->post(route('attachments.store'), ['attachable_type' => 'quote', 'attachable_id' => $quote->id,
            'type' => 'document', 'file' => UploadedFile::fake()->createWithContent('proposta.txt', 'Allegato dimostrativo')])->assertSessionHasNoErrors()->assertRedirect();
        $attachment = $quote->attachments()->sole();
        Storage::disk('attachments')->assertExists($attachment->path);
        $this->actingAs(User::factory()->create(['role' => UserRole::Administration]));
        $this->get(route('attachments.download', $attachment))->assertOk();
        $this->delete(route('attachments.destroy', $attachment))->assertRedirect();
        Storage::disk('attachments')->assertMissing($attachment->path);
        Livewire::test(AttachmentManager::class, ['model' => $quote])
            ->set('file', UploadedFile::fake()->createWithContent('documento.txt', 'Documento dimostrativo'))
            ->assertHasNoErrors()->assertSee('documento.txt');
    }

    public function test_commercial_can_read_only_attachments_of_visible_offers(): void
    {
        $visible = $this->quote('presented');
        $attachment = $this->attachment($visible);
        $draft = $this->attachment($this->quote('draft'));
        $foreign = $this->attachment($this->quote('presented', ['client_id' => Client::factory()->create()->id]));
        $this->actingAs($this->commercial);
        $this->get(route('quotes.show', $visible))->assertOk()->assertSee('test.txt')->assertDontSee('Carica allegato')->assertDontSee('Elimina offerta');
        $this->get(route('attachments.download', $attachment))->assertOk();
        $this->get(route('attachments.download', $draft))->assertForbidden();
        $this->get(route('attachments.download', $foreign))->assertForbidden();
        $this->delete(route('attachments.destroy', $attachment))->assertForbidden();
        Livewire::test(AttachmentManager::class, ['model' => $visible])->call('deleteAttachment', $attachment->id)->assertForbidden();
        Livewire::test(AttachmentManager::class, ['model' => $foreign->attachable])->assertForbidden();
    }

    public function test_deleted_revision_does_not_break_new_revisions_or_reopen_old_acceptance(): void
    {
        $original = $this->quote('presented');
        $this->post(route('quotes.revise', $original))->assertRedirect();
        $revision = $original->nextQuote()->sole();
        $this->delete(route('quotes.destroy', $revision))->assertRedirect();
        $this->post(route('quotes.accept', $original))->assertForbidden();
        $this->post(route('quotes.revise', $original))->assertRedirect();
        $next = Quote::where('previous_quote_id', $revision->id)->sole();
        $this->assertSame(3, $next->revision);
        $this->assertSame(1, $next->items()->count());
        $this->post(route('quotes.revise', $original))->assertRedirect(route('quotes.edit', $next));
        $this->assertDatabaseCount('quotes', 3);
    }
}
