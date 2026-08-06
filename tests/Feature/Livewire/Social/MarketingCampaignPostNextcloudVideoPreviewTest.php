<?php

namespace Tests\Feature\Livewire\Social;

use App\Domain\Social\Services\MarketingCampaignPostMediaUrlResolver;
use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\Social\MarketingCampaignPostType;
use App\Enums\UserRole;
use App\Livewire\Social\MarketingCampaigns\MarketingCampaignPostShow;
use App\Models\Client;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostMedia;
use App\Models\MarketingCampaignPostVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MarketingCampaignPostNextcloudVideoPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_nextcloud_video_uses_the_authenticated_stream_instead_of_the_image_preview(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Generated->value,
            'content_type' => MarketingCampaignPostType::Reel->value,
        ]);
        $nextcloudPath = '/VideoClienti/cliente-test/video.mp4';
        $media = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'source' => 'nextcloud',
            'disk' => null,
            'path' => null,
            'media_type' => 'video',
            'mime_type' => 'video/mp4',
            'original_name' => 'video.mp4',
            'nextcloud_path' => $nextcloudPath,
            'nextcloud_share_url' => 'https://nextcloud.example.test/s/video-share',
        ]);
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'version_number' => 1,
        ]);
        $version->mediaItems()->attach($media->id, ['sort_order' => 0]);
        $post->update(['current_version_id' => $version->id]);

        $this->actingAs($user);

        $expectedUrl = route('nextcloud.download', ['path' => $nextcloudPath]);

        Livewire::test(MarketingCampaignPostShow::class, [
            'campaign' => $campaign,
            'post' => $post->fresh(),
        ])
            ->assertSet('selected_media_items.0.type', 'video')
            ->assertSet('selected_media_items.0.preview_url', $expectedUrl)
            ->assertSeeHtml('src="'.$expectedUrl.'#t=0.001"')
            ->assertDontSeeHtml('https://nextcloud.example.test/s/video-share/preview');
    }

    public function test_saved_nextcloud_image_keeps_the_share_thumbnail(): void
    {
        $media = MarketingCampaignPostMedia::factory()->create([
            'source' => 'nextcloud',
            'media_type' => 'image',
            'mime_type' => 'image/jpeg',
            'nextcloud_path' => '/FotoClienti/cliente-test/foto.jpg',
            'nextcloud_share_url' => 'https://nextcloud.example.test/s/image-share',
        ]);

        $this->assertSame(
            'https://nextcloud.example.test/s/image-share/preview',
            app(MarketingCampaignPostMediaUrlResolver::class)->previewUrl($media)
        );
    }
}
