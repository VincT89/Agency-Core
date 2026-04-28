<?php

namespace Tests\Feature\Social;

use Tests\TestCase;
use App\Models\Client;
use App\Models\MarketingProject;
use App\Models\SocialPost;
use App\Domain\Social\Actions\MarkSocialPostAsPublishedAction;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MarkSocialPostAsPublishedActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_throws_validation_exception_if_meta_not_ready()
    {
        $client = Client::factory()->create();
        $project = \App\Models\Project::factory()->create(['client_id' => $client->id]);
        
        $marketingProject = MarketingProject::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Test',
            'type' => 'one_shot',
            'status' => \App\Enums\Social\MarketingProjectStatus::Draft->value,
            'platforms' => ['facebook', 'instagram', 'tiktok'],
        ]);

        $post = SocialPost::create([
            'marketing_project_id' => $marketingProject->id,
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Test Post',
            'status' => \App\Enums\Social\SocialPostStatus::ClientApproved,
            'created_by' => \App\Models\User::factory()->create()->id,
        ]);

        $action = new MarkSocialPostAsPublishedAction();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Pubblicazione bloccata: accessi Meta Business incompleti.');

        $action->execute($post);
    }
}
