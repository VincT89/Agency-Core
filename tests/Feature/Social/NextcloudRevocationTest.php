<?php

namespace Tests\Feature\Social;

use App\Domain\Social\Actions\CreateManualMarketingCampaignPostVersionAction;
use App\Domain\Social\DTOs\NextcloudFileInfo;
use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\Social\MarketingCampaignPostType;
use App\Enums\UserRole;
use App\Exceptions\NextcloudShareException;
use App\Exceptions\Social\StaleMarketingCampaignPostVersionException;
use App\Livewire\Social\MarketingCampaigns\MarketingCampaignPostCreate;
use App\Livewire\Social\MarketingCampaigns\MarketingCampaignPostShow;
use App\Models\Client;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\User;
use App\Services\Integrations\Nextcloud\DTO\NextcloudPublicShareResult;
use App\Services\Integrations\Nextcloud\NextcloudService;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class NextcloudRevocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_revoke_public_shares_exception_does_not_mask_original_error()
    {
        $owner = User::factory()->create(['role' => UserRole::Marketing->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'content_type' => MarketingCampaignPostType::Post->value,
            'status' => MarketingCampaignPostStatus::Draft->value,
        ]);

        $mockNextcloud = Mockery::mock(NextcloudService::class);
        $mockNextcloud->shouldReceive('acquireLocksForPaths')->once()->andReturn(['lock_instance']);
        $mockNextcloud->shouldReceive('releaseLocks')->once()->with(['lock_instance']);
        $mockNextcloud->shouldReceive('getFileInfo')
            ->once()
            ->with('/Photos/test.jpg')
            ->andReturn($this->fileInfo('/Photos/test.jpg', 'file-1'));

        $mockNextcloud->shouldReceive('ensurePublicShare')
            ->once()
            ->andReturn(new NextcloudPublicShareResult('https://nextcloud.example.com/s/123', 'share_123', true));

        // Simulate exception during revocation, enforce it is called once
        $mockNextcloud->shouldReceive('revokePublicShareById')->once()->with('share_123')->andThrow(new \Exception('Nextcloud API down'));
        $this->app->instance(NextcloudService::class, $mockNextcloud);

        $mockAction = Mockery::mock(CreateManualMarketingCampaignPostVersionAction::class);
        $mockAction->shouldReceive('execute')->once()->andThrow(new StaleMarketingCampaignPostVersionException('Stale error'));
        $this->app->instance(CreateManualMarketingCampaignPostVersionAction::class, $mockAction);

        Log::shouldReceive('error')->never()->with('Errore salvataggio manuale:');
        Log::shouldReceive('warning')->once()->with('Failed to revoke Nextcloud share during cleanup', Mockery::any());

        $this->withoutExceptionHandling();

        $component = Livewire::actingAs($owner)
            ->test(MarketingCampaignPostShow::class, ['campaign' => $campaign, 'post' => $post])
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
                    'nextcloud_path' => '/Photos/test.jpg',
                ],
            ])
            ->call('savePost');

        $component
            ->assertHasErrors([
                'post' => 'Il post è stato modificato in un\'altra sessione. Ricarica la pagina prima di continuare.',
            ])
            ->assertHasNoErrors(['post' => 'Stale error'])
            ->assertHasNoErrors(['post' => 'Nextcloud API down']);
    }

    public function test_no_revocation_if_share_preexists()
    {
        $owner = User::factory()->create(['role' => UserRole::Marketing->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'content_type' => MarketingCampaignPostType::Post->value,
            'status' => MarketingCampaignPostStatus::Draft->value,
        ]);

        $mockNextcloud = Mockery::mock(NextcloudService::class)->shouldIgnoreMissing();
        $mockNextcloud->shouldReceive('acquireLocksForPaths')->once()->with(['/Photos/test.jpg'])->andReturn([
            Mockery::mock(Lock::class)->shouldReceive('release')->getMock(),
        ]);
        $mockNextcloud->shouldReceive('getFileInfo')
            ->once()
            ->with('/Photos/test.jpg')
            ->andReturn($this->fileInfo('/Photos/test.jpg', 'file-1'));

        $mockNextcloud->shouldReceive('ensurePublicShare')
            ->once()
            ->with('/Photos/test.jpg')
            ->andReturn(new NextcloudPublicShareResult('url', '111', false));

        // NEVER revoke
        $mockNextcloud->shouldReceive('revokePublicShareById')->never();

        $this->app->instance(NextcloudService::class, $mockNextcloud);

        $mockAction = Mockery::mock(CreateManualMarketingCampaignPostVersionAction::class);
        $mockAction->shouldReceive('execute')->once()->andThrow(new \Exception('Generic error'));
        $this->app->instance(CreateManualMarketingCampaignPostVersionAction::class, $mockAction);

        $component = Livewire::actingAs($owner)
            ->test(MarketingCampaignPostShow::class, ['campaign' => $campaign, 'post' => $post])
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
                    'nextcloud_path' => '/Photos/test.jpg',
                ],
            ])
            ->call('savePost');
        $component->assertHasNoErrors();
    }

    public function test_only_revoke_newly_created_shares()
    {
        $owner = User::factory()->create(['role' => UserRole::Marketing->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'content_type' => MarketingCampaignPostType::Post->value,
            'status' => MarketingCampaignPostStatus::Draft->value,
        ]);

        $mockNextcloud = Mockery::mock(NextcloudService::class)->shouldIgnoreMissing();
        $mockNextcloud->shouldReceive('acquireLocksForPaths')->once()->with(['/Photos/1.jpg', '/Photos/2.jpg'])->andReturn([
            Mockery::mock(Lock::class)->shouldReceive('release')->getMock(),
        ]);
        $mockNextcloud->shouldReceive('getFileInfo')
            ->once()
            ->with('/Photos/1.jpg')
            ->andReturn($this->fileInfo('/Photos/1.jpg', 'file-1'));
        $mockNextcloud->shouldReceive('getFileInfo')
            ->once()
            ->with('/Photos/2.jpg')
            ->andReturn($this->fileInfo('/Photos/2.jpg', 'file-2'));

        $mockNextcloud->shouldReceive('ensurePublicShare')
            ->once()
            ->with('/Photos/1.jpg')
            ->andReturn(new NextcloudPublicShareResult('url1', '111', false));

        $mockNextcloud->shouldReceive('ensurePublicShare')
            ->once()
            ->with('/Photos/2.jpg')
            ->andReturn(new NextcloudPublicShareResult('url2', '222', true));

        // Revoke only 222
        $mockNextcloud->shouldReceive('revokePublicShareById')->once()->with('222');
        $mockNextcloud->shouldReceive('revokePublicShareById')->with('111')->never();

        $this->app->instance(NextcloudService::class, $mockNextcloud);

        $mockAction = Mockery::mock(CreateManualMarketingCampaignPostVersionAction::class);
        $mockAction->shouldReceive('execute')->once()->andThrow(new \Exception('Generic error'));
        $this->app->instance(CreateManualMarketingCampaignPostVersionAction::class, $mockAction);

        $component = Livewire::actingAs($owner)
            ->test(MarketingCampaignPostShow::class, ['campaign' => $campaign, 'post' => $post])
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
                    'nextcloud_path' => '/Photos/1.jpg',
                ],
                [
                    'uid' => 'nc:2',
                    'source' => 'nextcloud',
                    'type' => 'image',
                    'name' => '2.jpg',
                    'nextcloud_path' => '/Photos/2.jpg',
                ],
            ])
            ->call('savePost');
    }

    public function test_marketing_campaign_post_create_revokes_share_on_error()
    {
        $owner = User::factory()->create(['role' => UserRole::Marketing->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);

        $mockNextcloud = Mockery::mock(NextcloudService::class)->shouldIgnoreMissing();
        $mockNextcloud->shouldReceive('acquireLocksForPaths')->once()->with(['/Photos/create.jpg'])->andReturn([
            Mockery::mock(Lock::class)->shouldReceive('release')->getMock(),
        ]);
        $mockNextcloud->shouldReceive('getFileInfo')
            ->once()
            ->with('/Photos/create.jpg')
            ->andReturn($this->fileInfo('/Photos/create.jpg', 'file-create'));

        $mockNextcloud->shouldReceive('ensurePublicShare')
            ->once()
            ->with('/Photos/create.jpg')
            ->andReturn(new NextcloudPublicShareResult('url', '999', true));

        $mockNextcloud->shouldReceive('revokePublicShareById')->once()->with('999');
        $this->app->instance(NextcloudService::class, $mockNextcloud);

        DB::shouldReceive('transaction')
            ->once()
            ->andThrow(new \Exception('Create error'));
        $component = Livewire::actingAs($owner)
            ->test(MarketingCampaignPostCreate::class, ['campaign' => $campaign])
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
                    'nextcloud_path' => '/Photos/create.jpg',
                ],
            ]);

        try {
            $component->call('save');
        } catch (\Exception $e) {
            $this->assertEquals('Create error', $e->getMessage());
        }
    }

    public function test_lock_timeout_handles_exception()
    {
        $owner = User::factory()->create(['role' => UserRole::Marketing->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'content_type' => MarketingCampaignPostType::Post->value,
            'status' => MarketingCampaignPostStatus::Draft->value,
        ]);

        $mockNextcloud = Mockery::mock(NextcloudService::class)->shouldIgnoreMissing();
        $mockNextcloud->shouldReceive('acquireLocksForPaths')
            ->once()
            ->with(['/Photos/test.jpg'])
            ->andThrow(new NextcloudShareException('Timeout acquisizione lock sui path'));

        $this->app->instance(NextcloudService::class, $mockNextcloud);

        $component = Livewire::actingAs($owner)
            ->test(MarketingCampaignPostShow::class, ['campaign' => $campaign, 'post' => $post])
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
                    'nextcloud_path' => '/Photos/test.jpg',
                ],
            ])
            ->call('savePost');

        $component->assertHasErrors('form.nextcloud_path');
    }

    private function fileInfo(
        string $path,
        string $fileId
    ): NextcloudFileInfo {
        return new NextcloudFileInfo(
            path: $path,
            fileId: $fileId,
            etag: "etag-{$fileId}",
            mimeType: 'image/jpeg',
            sizeBytes: 1024,
        );
    }
}
