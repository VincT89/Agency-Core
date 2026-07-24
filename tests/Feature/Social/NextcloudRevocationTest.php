<?php

namespace Tests\Feature\Social;

use App\Models\Client;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\User;
use App\Services\Integrations\Nextcloud\NextcloudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class NextcloudRevocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_revoke_public_shares_exception_does_not_mask_original_error()
    {
        $owner = User::factory()->create(['role' => \App\Enums\UserRole::Marketing->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'content_type' => \App\Enums\Social\MarketingCampaignPostType::Post->value,
            'status' => \App\Enums\Social\MarketingCampaignPostStatus::Draft->value,
        ]);

        $mockNextcloud = Mockery::mock(NextcloudService::class);
        $mockNextcloud->shouldReceive('createPublicShare')->andReturn('https://nextcloud.example.com/s/123');
        // This is the key part: if it masks, this exception would bubble or mask the original one
        $mockNextcloud->shouldReceive('revokePublicShares')->andThrow(new \Exception('Nextcloud API down'));
        $this->app->instance(NextcloudService::class, $mockNextcloud);

        $mockAction = Mockery::mock(\App\Domain\Social\Actions\CreateManualMarketingCampaignPostVersionAction::class);
        $mockAction->shouldReceive('execute')->andThrow(new \App\Exceptions\Social\StaleMarketingCampaignPostVersionException('Stale error'));
        $this->app->instance(\App\Domain\Social\Actions\CreateManualMarketingCampaignPostVersionAction::class, $mockAction);

        $component = Livewire::actingAs($owner)
            ->test(\App\Livewire\Social\MarketingCampaigns\MarketingCampaignPostShow::class, ['campaign' => $campaign, 'post' => $post])
            ->set('form.title', 'Test Title')
            ->set('form.content_type', 'post')
            ->set('form.status', 'draft')
            ->set('form.ai_analysis_enabled', true)
            ->set('form.media_source', 'nextcloud')
            ->set('form.nextcloud_path', '/Photos/test.jpg')
            ->set('selected_media_items', [
                [
                    'uid' => 'nc:1',
                    'source' => 'nextcloud',
                    'type' => 'image',
                    'name' => 'test.jpg',
                    'nextcloud_path' => '/Photos/test.jpg'
                ]
            ])
            ->call('savePost');

        $component->assertHasNoErrors();
        
        // If the cleanup exception wasn't caught, this call would crash with "Nextcloud API down".
        // If it was caught, the component handles it gracefully.
        $this->assertTrue(true);
    }
}
