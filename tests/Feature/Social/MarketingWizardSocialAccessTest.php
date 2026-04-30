<?php

namespace Tests\Feature\Social;

use App\Models\User;
use App\Models\Client;
use App\Enums\Social\SocialPlatform;
use App\Enums\Social\SocialAccessMethod;
use App\Enums\Social\SocialAccessStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MarketingWizardSocialAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_wizard_blocks_n8n_submission_if_meta_required_but_not_ready()
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();

        // FB ready but IG missing -> Meta is NOT ready
        $client->socialAccounts()->create([
            'platform' => SocialPlatform::Facebook->value,
            'is_ready_to_publish' => true,
            'access_status' => SocialAccessStatus::ReadyToPublish->value,
            'business_manager_id' => '12345',
            'access_method' => SocialAccessMethod::MetaBusiness->value,
        ]);

        $projectModel = \App\Models\Project::factory()->create(['client_id' => $client->id]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Social\MarketingProjects\MarketingProjectCreate::class)
            ->set('step', 1)
            ->set('client_id', $client->id)
            ->set('project_mode', 'existing')
            ->set('project_id', $projectModel->id)
            ->call('nextStep')
            ->set('service_type', 'social_management')
            ->set('campaign_structure', 'one_shot')
            ->call('nextStep')
            ->set('title', 'Test Project')
            ->set('brief', 'Test brief')
            ->set('service_options.platforms', ['facebook', 'instagram'])
            ->set('service_options.frequency', '3 post')
            ->set('shooting_mode', 'none')
            ->call('nextStep')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(); // Project saved correctly

        $project = \App\Models\MarketingProject::first();
        $this->assertEquals(\App\Enums\Social\MarketingProjectStatus::Draft, $project->status);

        // Try submitting to n8n
        $action = app(\App\Domain\Social\Actions\SubmitMarketingProjectToN8nAction::class);
        
        try {
            $action->execute($project);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('social_access', $e->errors());
        }
    }
}
