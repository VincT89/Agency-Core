<?php

namespace Tests\Feature\Social;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\MarketingProject;
use App\Models\SocialPost;
use App\Enums\Social\SocialPostStatus;
use App\Enums\Social\PublicationMode;
use App\Enums\Social\SocialPlatform;
use App\Enums\Social\SocialAccessStatus;
use App\Enums\Social\SocialAccessMethod;
use Livewire\Livewire;

class PublicationBoardSocialAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketing_wizard_shows_warning_if_meta_not_ready()
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        
        Livewire::actingAs($user)
            ->test(\App\Livewire\Social\MarketingProjects\MarketingProjectCreate::class)
            ->set('step', 1)
            ->set('client_id', $client->id)
            ->call('nextStep')
            ->set('type', 'one_shot')
            ->call('nextStep')
            ->set('title', 'Test Project')
            ->set('brief', 'Test brief')
            ->set('platforms', ['facebook', 'instagram'])
            ->assertSee('Stato Accessi Social')
            ->assertSee('Meta Business è richiesto per l', false)
            ->set('publication_mode', 'manual')
            ->call('nextStep');
    }

    public function test_publication_board_blocks_publication_if_meta_not_ready()
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $genericProject = \App\Models\Project::factory()->create(['client_id' => $client->id]);
        
        $project = MarketingProject::create([
            'client_id' => $client->id,
            'project_id' => $genericProject->id,
            'title' => 'Test Project',
            'brief' => 'Brief test',
            'type' => 'editorial_plan',
            'status' => \App\Enums\Social\MarketingProjectStatus::Draft,
            'publication_mode' => PublicationMode::Manual,
            'platforms' => ['facebook', 'instagram'],
        ]);

        $post = SocialPost::create([
            'marketing_project_id' => $project->id,
            'client_id' => $client->id,
            'project_id' => $genericProject->id,
            'title' => 'Test Post',
            'status' => SocialPostStatus::ClientApproved,
            'publication_status' => \App\Enums\Social\PublicationStatus::Ready,
            'publication_mode' => PublicationMode::Manual,
            'platforms' => ['facebook', 'instagram'],
        ]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Social\PublicationBoard::class)
            ->assertSee('Pubblicazione bloccata: è richiesto l', false);
    }

    public function test_publication_board_allows_publication_if_meta_is_ready()
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        
        // Make Meta Ready
        $client->socialAccounts()->create([
            'platform' => SocialPlatform::Facebook->value,
            'is_ready_to_publish' => true,
            'access_status' => SocialAccessStatus::ReadyToPublish,
            'access_method' => SocialAccessMethod::MetaBusiness,
            'business_manager_id' => '123',
        ]);
        $client->socialAccounts()->create([
            'platform' => SocialPlatform::Instagram->value,
            'is_ready_to_publish' => true,
            'access_status' => SocialAccessStatus::ReadyToPublish,
            'access_method' => SocialAccessMethod::MetaBusiness,
            'business_manager_id' => '123',
        ]);

        $genericProject = \App\Models\Project::factory()->create(['client_id' => $client->id]);
        
        $project = MarketingProject::create([
            'client_id' => $client->id,
            'project_id' => $genericProject->id,
            'title' => 'Test Project',
            'brief' => 'Brief test',
            'type' => 'editorial_plan',
            'status' => \App\Enums\Social\MarketingProjectStatus::Draft,
            'publication_mode' => PublicationMode::Manual,
            'platforms' => ['facebook', 'instagram'],
        ]);

        $post = SocialPost::create([
            'marketing_project_id' => $project->id,
            'client_id' => $client->id,
            'project_id' => $genericProject->id,
            'title' => 'Test Post',
            'status' => SocialPostStatus::ClientApproved,
            'publication_status' => \App\Enums\Social\PublicationStatus::Ready,
            'publication_mode' => PublicationMode::Manual,
            'platforms' => ['facebook', 'instagram'],
        ]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Social\PublicationBoard::class)
            ->assertDontSee('Pubblicazione bloccata: è richiesto l', false);
    }
}
