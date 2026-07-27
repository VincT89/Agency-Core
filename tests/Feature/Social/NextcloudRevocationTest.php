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
        $mockNextcloud->shouldReceive('acquireLocksForPaths')->once()->andReturn(['lock_instance']);
        $mockNextcloud->shouldReceive('releaseLocks')->once()->with(['lock_instance']);
        
        $mockNextcloud->shouldReceive('ensurePublicShare')
            ->once()
            ->andReturn(new \App\Services\Integrations\Nextcloud\DTO\NextcloudPublicShareResult('https://nextcloud.example.com/s/123', 'share_123', true));
            
        // Simulate exception during revocation, enforce it is called once
        $mockNextcloud->shouldReceive('revokePublicShareById')->once()->with('share_123')->andThrow(new \Exception('Nextcloud API down'));
        $this->app->instance(NextcloudService::class, $mockNextcloud);

        $mockAction = Mockery::mock(\App\Domain\Social\Actions\CreateManualMarketingCampaignPostVersionAction::class);
        $mockAction->shouldReceive('execute')->once()->andThrow(new \App\Exceptions\Social\StaleMarketingCampaignPostVersionException('Stale error'));
        $this->app->instance(\App\Domain\Social\Actions\CreateManualMarketingCampaignPostVersionAction::class, $mockAction);

        \Illuminate\Support\Facades\Log::shouldReceive('error')->never()->with('Errore salvataggio manuale:');
        \Illuminate\Support\Facades\Log::shouldReceive('warning')->once()->with('Failed to revoke Nextcloud share during cleanup', \Mockery::any());

        $this->withoutExceptionHandling();

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
            
        $component->assertHasErrors(['post' => 'Stale error']);
    }

    public function test_no_revocation_if_share_preexists()
    {
        $owner = User::factory()->create(['role' => \App\Enums\UserRole::Marketing->value]);
        $client = \App\Models\Client::factory()->create();
        $campaign = \App\Models\MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = \App\Models\MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'content_type' => \App\Enums\Social\MarketingCampaignPostType::Post->value,
            'status' => \App\Enums\Social\MarketingCampaignPostStatus::Draft->value,
        ]);

        $mockNextcloud = \Mockery::mock(\App\Services\Integrations\Nextcloud\NextcloudService::class)->shouldIgnoreMissing();
        $mockNextcloud->shouldReceive('acquireLocksForPaths')->once()->with(['/Photos/test.jpg'])->andReturn([
            \Mockery::mock(\Illuminate\Contracts\Cache\Lock::class)->shouldReceive('release')->getMock()
        ]);
        
        $mockNextcloud->shouldReceive('ensurePublicShare')
            ->once()
            ->with('/Photos/test.jpg')
            ->andReturn(new \App\Services\Integrations\Nextcloud\DTO\NextcloudPublicShareResult('url', '111', false));

        // NEVER revoke
        $mockNextcloud->shouldReceive('revokePublicShareById')->never();
        
        $this->app->instance(\App\Services\Integrations\Nextcloud\NextcloudService::class, $mockNextcloud);

        $mockAction = \Mockery::mock(\App\Domain\Social\Actions\CreateManualMarketingCampaignPostVersionAction::class);
        $mockAction->shouldReceive('execute')->once()->andThrow(new \Exception('Generic error'));
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
    }

    public function test_only_revoke_newly_created_shares()
    {
        $owner = User::factory()->create(['role' => \App\Enums\UserRole::Marketing->value]);
        $client = \App\Models\Client::factory()->create();
        $campaign = \App\Models\MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = \App\Models\MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'content_type' => \App\Enums\Social\MarketingCampaignPostType::Post->value,
            'status' => \App\Enums\Social\MarketingCampaignPostStatus::Draft->value,
        ]);

        $mockNextcloud = \Mockery::mock(\App\Services\Integrations\Nextcloud\NextcloudService::class)->shouldIgnoreMissing();
        $mockNextcloud->shouldReceive('acquireLocksForPaths')->once()->with(['/Photos/1.jpg', '/Photos/2.jpg'])->andReturn([
            \Mockery::mock(\Illuminate\Contracts\Cache\Lock::class)->shouldReceive('release')->getMock()
        ]);
        
        $mockNextcloud->shouldReceive('ensurePublicShare')
            ->once()
            ->with('/Photos/1.jpg')
            ->andReturn(new \App\Services\Integrations\Nextcloud\DTO\NextcloudPublicShareResult('url1', '111', false));
            
        $mockNextcloud->shouldReceive('ensurePublicShare')
            ->once()
            ->with('/Photos/2.jpg')
            ->andReturn(new \App\Services\Integrations\Nextcloud\DTO\NextcloudPublicShareResult('url2', '222', true));

        // Revoke only 222
        $mockNextcloud->shouldReceive('revokePublicShareById')->once()->with('222');
        $mockNextcloud->shouldReceive('revokePublicShareById')->with('111')->never();
        
        $this->app->instance(\App\Services\Integrations\Nextcloud\NextcloudService::class, $mockNextcloud);

        $mockAction = \Mockery::mock(\App\Domain\Social\Actions\CreateManualMarketingCampaignPostVersionAction::class);
        $mockAction->shouldReceive('execute')->once()->andThrow(new \Exception('Generic error'));
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
                    'name' => '1.jpg',
                    'nextcloud_path' => '/Photos/1.jpg'
                ],
                [
                    'uid' => 'nc:2',
                    'source' => 'nextcloud',
                    'type' => 'image',
                    'name' => '2.jpg',
                    'nextcloud_path' => '/Photos/2.jpg'
                ]
            ])
            ->call('savePost');
    }

    public function test_marketing_campaign_post_create_revokes_share_on_error()
    {
        $owner = User::factory()->create(['role' => \App\Enums\UserRole::Marketing->value]);
        $client = \App\Models\Client::factory()->create();
        $campaign = \App\Models\MarketingCampaign::factory()->create(['client_id' => $client->id]);

        $mockNextcloud = \Mockery::mock(\App\Services\Integrations\Nextcloud\NextcloudService::class)->shouldIgnoreMissing();
        $mockNextcloud->shouldReceive('acquireLocksForPaths')->once()->with(['/Photos/create.jpg'])->andReturn([
            \Mockery::mock(\Illuminate\Contracts\Cache\Lock::class)->shouldReceive('release')->getMock()
        ]);
        
        $mockNextcloud->shouldReceive('ensurePublicShare')
            ->once()
            ->with('/Photos/create.jpg')
            ->andReturn(new \App\Services\Integrations\Nextcloud\DTO\NextcloudPublicShareResult('url', '999', true));

        $mockNextcloud->shouldReceive('revokePublicShareById')->once()->with('999');
        $this->app->instance(\App\Services\Integrations\Nextcloud\NextcloudService::class, $mockNextcloud);

        \Illuminate\Support\Facades\DB::shouldReceive('transaction')
            ->once()
            ->andThrow(new \Exception('Create error'));
        $component = Livewire::actingAs($owner)
            ->test(\App\Livewire\Social\MarketingCampaigns\MarketingCampaignPostCreate::class, ['campaign' => $campaign])
            ->set('form.title', 'Test Title')
            ->set('form.content_type', 'post')
            ->set('form.status', 'draft')
            ->set('form.ai_analysis_enabled', true)
            ->set('form.media_source', 'nextcloud')
            ->set('form.nextcloud_path', '/Photos/create.jpg')
            ->set('selected_media_items', [
                [
                    'uid' => 'nc:create',
                    'source' => 'nextcloud',
                    'type' => 'image',
                    'name' => 'create.jpg',
                    'nextcloud_path' => '/Photos/create.jpg'
                ]
            ]);

            try {
                $component->call('save');
            } catch (\Exception $e) {
                $this->assertEquals('Create error', $e->getMessage());
            }
    }

    public function test_lock_timeout_handles_exception()
    {
        $owner = User::factory()->create(['role' => \App\Enums\UserRole::Marketing->value]);
        $client = \App\Models\Client::factory()->create();
        $campaign = \App\Models\MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = \App\Models\MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'content_type' => \App\Enums\Social\MarketingCampaignPostType::Post->value,
            'status' => \App\Enums\Social\MarketingCampaignPostStatus::Draft->value,
        ]);

        $mockNextcloud = \Mockery::mock(\App\Services\Integrations\Nextcloud\NextcloudService::class)->shouldIgnoreMissing();
        $mockNextcloud->shouldReceive('acquireLocksForPaths')
            ->once()
            ->with(['/Photos/test.jpg'])
            ->andThrow(new \App\Exceptions\NextcloudShareException("Timeout acquisizione lock sui path"));
            
        $this->app->instance(\App\Services\Integrations\Nextcloud\NextcloudService::class, $mockNextcloud);

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

        $component->assertHasErrors('form.nextcloud_path');
    }
}
