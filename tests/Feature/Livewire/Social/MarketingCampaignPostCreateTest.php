<?php

namespace Tests\Feature\Livewire\Social;

use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\Social\MarketingCampaignPostVersionSource;
use App\Livewire\Social\MarketingCampaigns\MarketingCampaignPostCreate;
use App\Models\Client;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use App\Domain\Social\Actions\SubmitMarketingCampaignPostToN8nAction;
use Mockery\MockInterface;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;
use App\Jobs\SendMarketingCampaignPostToN8nJob;
use Illuminate\Support\Str;

class MarketingCampaignPostCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Event::fake();
    }

    public function test_save_and_submit_to_n8n_calls_action_without_assigning_status_in_livewire()
    {
        $user = User::factory()->create(['role' => \App\Enums\UserRole::Admin->value]);
        
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);

        $this->actingAs($user);

        // We'll mock the Action to ensure it's called and it's the one responsible for the status
        $this->mock(SubmitMarketingCampaignPostToN8nAction::class, function (MockInterface $mock) {
            $mock->shouldReceive('execute')
                 ->once()
                 ->andReturnUsing(function ($post, $runtimeClientData) {
                     // Inside the action, it is expected to set the status
                     // We just fake the action logic partly to prove Livewire didn't set it first
                     $this->assertNotEquals(MarketingCampaignPostStatus::PendingN8n->value, $post->status->value, 'Livewire should not set status to pending_n8n directly before Action');
                     $post->status = MarketingCampaignPostStatus::PendingN8n->value;
                     $post->save();
                 });
        });

        Livewire::test(MarketingCampaignPostCreate::class, ['campaign' => $campaign])
            ->set('form.title', 'Test Title')
            ->set('form.description', 'Test Description')
            ->set('form.content_type', 'post')
            ->set('form.status', 'draft')
            ->set('form.ai_analysis_enabled', false)
            ->set('form.media_source', 'local')
            ->call('saveAndSubmitToN8n')
            ->assertRedirect();

        $post = MarketingCampaignPost::where('marketing_campaign_id', $campaign->id)->first();
        $this->assertNotNull($post);
        $this->assertEquals(MarketingCampaignPostStatus::PendingN8n, $post->status);
    }

    public function test_save_and_submit_to_n8n_dispatches_job_with_new_request_id()
    {
        $user = User::factory()->create(['role' => \App\Enums\UserRole::Admin->value]);
        
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);

        $this->actingAs($user);

        Livewire::test(MarketingCampaignPostCreate::class, ['campaign' => $campaign])
            ->set('form.title', 'Test Title')
            ->set('form.description', 'Test Description')
            ->set('form.content_type', 'post')
            ->set('form.status', 'draft')
            ->set('form.ai_analysis_enabled', false)
            ->set('form.media_source', 'local')
            ->call('saveAndSubmitToN8n');

        Queue::assertPushed(SendMarketingCampaignPostToN8nJob::class, function ($job) {
            return !empty($job->post->n8n_request_id) && str_starts_with($job->post->n8n_request_id, 'cmp_');
        });
    }

    public function test_sody_loader_is_available_and_resets_after_validation_error(): void
    {
        $user = User::factory()->create(['role' => \App\Enums\UserRole::Admin->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);

        $this->actingAs($user);

        Livewire::test(MarketingCampaignPostCreate::class, ['campaign' => $campaign])
            ->assertSee('Sody sta preparando il contenuto')
            ->assertSee('Chiudi questo pannello')
            ->assertDontSee('this.$cleanup')
            ->assertDontSee('Interrompi')
            ->set('form.content_type', 'unsupported')
            ->call('saveAndSubmitToN8n')
            ->assertHasErrors(['form.content_type'])
            ->assertDispatched('sody-processing-started')
            ->assertDispatched('sody-processing-failed');
    }

    public function test_manual_ready_button_is_only_visible_when_sody_is_disabled(): void
    {
        $user = User::factory()->create(['role' => \App\Enums\UserRole::Admin->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);

        $this->actingAs($user);

        Livewire::test(MarketingCampaignPostCreate::class, ['campaign' => $campaign])
            ->assertDontSee('Salva come pronto senza Sody')
            ->set('form.ai_analysis_enabled', false)
            ->assertSee('Salva come pronto senza Sody')
            ->assertDontSee('Salva e genera solo testo');
    }

    public function test_manual_ready_creation_keeps_uploaded_media_and_creates_manual_version(): void
    {
        Storage::fake('social_media');

        $user = User::factory()->create(['role' => \App\Enums\UserRole::Admin->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $image = UploadedFile::fake()->image('manual-image.jpg');

        $this->actingAs($user);

        Livewire::test(MarketingCampaignPostCreate::class, ['campaign' => $campaign])
            ->set('form.title', 'Post manuale')
            ->set('form.description', 'Testo scritto senza Sody')
            ->set('form.content_type', 'post')
            ->set('form.status', 'draft')
            ->set('form.media_source', 'local')
            ->set('media', [$image])
            ->assertSee('manual-image.jpg')
            ->set('form.ai_analysis_enabled', false)
            ->assertSee('manual-image.jpg')
            ->call('saveAsManualVersion')
            ->assertHasNoErrors()
            ->assertRedirect();

        $post = MarketingCampaignPost::query()
            ->where('marketing_campaign_id', $campaign->id)
            ->firstOrFail();

        $this->assertSame(MarketingCampaignPostStatus::Generated, $post->status);
        $this->assertNotNull($post->current_version_id);
        $this->assertSame(MarketingCampaignPostVersionSource::Manual, $post->currentVersion->source);
        $this->assertCount(1, $post->currentVersion->mediaItems);
        Storage::disk('social_media')->assertExists($post->currentVersion->mediaItems->first()->path);
    }
}
