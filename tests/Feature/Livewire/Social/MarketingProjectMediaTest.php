<?php

namespace Tests\Feature\Livewire\Social;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Livewire\Social\MarketingProjects\MarketingProjectCreate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use App\Models\MarketingProject;

class MarketingProjectMediaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'role' => 'admin',
        ]);
        
        $this->client = Client::factory()->create();
        
        Storage::fake('public');
    }

    public function test_can_upload_local_media_and_save_to_project()
    {
        $project = Project::factory()->create(['client_id' => $this->client->id]);
        $project->users()->attach($this->user->id, ['role' => 'manager']);

        $file1 = UploadedFile::fake()->image('foto1.jpg')->size(100);
        $file2 = UploadedFile::fake()->image('foto2.png')->size(200);

        Livewire::actingAs($this->user)
            ->test(MarketingProjectCreate::class)
            ->set('client_id', $this->client->id)
            ->set('project_mode', 'existing')
            ->set('project_id', $project->id)
            ->set('service_type', 'social_management')
            ->set('campaign_structure', 'one_shot')
            ->call('nextStep')
            ->set('title', 'Campaign Test Media')
            ->set('brief', 'briefing test')
            ->set('service_options.platforms', ['instagram'])
            ->set('service_options.frequency', '2 post')
            ->set('shooting_mode', 'none')
            ->set('uploaded_media', [$file1, $file2])
            ->call('nextStep') // -> step 4 (skip to 5)
            ->call('save');

        $marketingProject = MarketingProject::where('title', 'Campaign Test Media')->first();
        
        $this->assertNotNull($marketingProject);
        $this->assertCount(2, $marketingProject->media);
        
        $media1 = $marketingProject->media()->where('original_name', 'foto1.jpg')->first();
        $this->assertNotNull($media1);
        $this->assertEquals('local', $media1->source);
        $this->assertEquals('public', $media1->disk);
        Storage::disk('public')->assertExists($media1->path);
    }

    public function test_can_toggle_nextcloud_file_with_size_limit()
    {
        Livewire::actingAs($this->user)
            ->test(MarketingProjectCreate::class)
            ->call('toggleNextcloudFile', '/photos/test.jpg', 'test.jpg', 5 * 1024 * 1024, 'image/jpeg')
            ->assertSet('selected_nextcloud_files', [
                ['path' => '/photos/test.jpg', 'name' => 'test.jpg', 'size' => 5242880, 'mime' => 'image/jpeg']
            ])
            ->call('toggleNextcloudFile', '/photos/test.jpg', 'test.jpg', 5 * 1024 * 1024, 'image/jpeg')
            ->assertSet('selected_nextcloud_files', [])
            ->call('toggleNextcloudFile', '/photos/huge.jpg', 'huge.jpg', 25 * 1024 * 1024, 'image/jpeg')
            ->assertHasErrors(['nextcloud_files']);
    }
}
