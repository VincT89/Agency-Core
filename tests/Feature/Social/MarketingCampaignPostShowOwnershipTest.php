<?php

namespace Tests\Feature\Social;

use App\Models\Client;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\User;
use App\Models\MarketingCampaignPostPublication;
use App\Enums\Social\SocialPlatform;
use App\Enums\Social\PublicationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class MarketingCampaignPostShowOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_owner_cannot_retry_or_force_fail_publication()
    {
        Queue::fake();

        $owner = User::factory()->create(['role' => \App\Enums\UserRole::Marketing->value]);
        $otherUser = User::factory()->create(['role' => \App\Enums\UserRole::Photographer->value]);

        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'content_type' => \App\Enums\Social\MarketingCampaignPostType::Post->value,
        ]);
        $publication = MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'platform' => SocialPlatform::Facebook->value,
            'status' => PublicationStatus::Failed->value,
        ]);

        // Access as non-marketing user
        Livewire::actingAs($otherUser)
            ->test(\App\Livewire\Social\MarketingCampaigns\MarketingCampaignPostShow::class, ['campaign' => $campaign, 'post' => $post])
            ->assertForbidden(); // The whole component fails to mount due to authorize('view')

        Queue::assertNothingPushed();
    }

    public function test_cannot_retry_publication_belonging_to_another_post()
    {
        Queue::fake();

        $owner = User::factory()->create(['role' => \App\Enums\UserRole::Marketing->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        
        $postA = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'content_type' => \App\Enums\Social\MarketingCampaignPostType::Post->value,
        ]);
        
        $postB = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'content_type' => \App\Enums\Social\MarketingCampaignPostType::Post->value,
        ]);

        $publicationOnPostB = MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $postB->id,
            'platform' => SocialPlatform::Facebook->value,
            'status' => PublicationStatus::Failed->value,
        ]);

        // Access Post A but pass Publication ID from Post B
        Livewire::actingAs($owner)
            ->test(\App\Livewire\Social\MarketingCampaigns\MarketingCampaignPostShow::class, ['campaign' => $campaign, 'post' => $postA])
            ->call('retryPublication', $publicationOnPostB->id)
            ->assertHasNoErrors(); // Safely returns because it's not found in $postA's publications

        $publicationOnPostB->refresh();
        $this->assertEquals(PublicationStatus::Failed, $publicationOnPostB->status);
        Queue::assertNothingPushed();
    }

    public function test_instagram_cancellation_branch_on_retry()
    {
        Queue::fake();

        $owner = User::factory()->create(['role' => \App\Enums\UserRole::Marketing->value]);
        $client = Client::factory()->create();
        $account = \App\Models\ClientSocialAccount::factory()->create([
            'client_id' => $client->id,
            'platform' => \App\Enums\Social\SocialPlatform::Instagram,
            'provider_account_id' => '123456',
        ]);
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'content_type' => \App\Enums\Social\MarketingCampaignPostType::Post->value,
        ]);
        
        $version = \App\Models\MarketingCampaignPostVersion::factory()->create(['marketing_campaign_post_id' => $post->id]);
        $post->update(['current_version_id' => $version->id]);
        
        $publication = MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'client_social_account_id' => $account->id,
            'platform' => SocialPlatform::Instagram->value,
            'status' => PublicationStatus::Failed->value,
            'snapshot_schema_version' => '1.0',
            'snapshot_hash' => 'dummy',
            'attempt_count' => 1,
            'payload_snapshot' => [],
        ]);

        $verifierMock = \Mockery::mock(\App\Domain\Social\Services\MarketingCampaignPostPublicationIntegrityVerifier::class);
        $verifierMock->shouldReceive('verify')->andReturn(new \App\Domain\Social\DTOs\PublicationIntegrityResult(
            passed: true,
            severity: \App\Enums\Social\IntegritySeverity::Error,
            errors: []
        ));
        $this->app->instance(\App\Domain\Social\Services\MarketingCampaignPostPublicationIntegrityVerifier::class, $verifierMock);

        Livewire::actingAs($owner)
            ->test(\App\Livewire\Social\MarketingCampaigns\MarketingCampaignPostShow::class, ['campaign' => $campaign, 'post' => $post])
            ->call('retryPublication', $publication->id);

        $publication->refresh();
        
        $this->assertEquals(PublicationStatus::Superseded, $publication->status);
        $this->assertEquals('Dismesso (sostituito da nuovo tentativo)', $publication->error_message);
        
        Queue::assertPushed(\App\Jobs\Social\ExecuteMarketingCampaignPostPublicationJob::class, function ($job) use ($publication) {
            return $job->publicationId !== $publication->id;
        });
    }
}

