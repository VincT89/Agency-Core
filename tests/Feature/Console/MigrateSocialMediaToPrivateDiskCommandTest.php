<?php

namespace Tests\Feature\Console;

use App\Models\MarketingCampaignPostMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MigrateSocialMediaToPrivateDiskCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('social_media');
    }

    public function test_dry_run_leaves_public_media_unchanged(): void
    {
        $media = $this->publicMedia('marketing/campaign-posts/image.jpg');

        $this->artisan('social:migrate-media-to-private')
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertSame('public', $media->fresh()->disk);
        Storage::disk('public')->assertExists($media->path);
        Storage::disk('social_media')->assertMissing($media->path);
    }

    public function test_execute_copies_verifies_updates_and_removes_public_file(): void
    {
        $first = $this->publicMedia('marketing/campaign-posts/shared.jpg');
        $second = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $first->marketing_campaign_post_id,
            'source' => 'n8n',
            'disk' => 'public',
            'path' => $first->path,
            'mime_type' => 'image/jpeg',
            'media_type' => 'image',
        ]);

        $this->artisan(
            'social:migrate-media-to-private',
            ['--execute' => true]
        )->assertSuccessful();

        $this->assertSame('social_media', $first->fresh()->disk);
        $this->assertSame('social_media', $second->fresh()->disk);
        $this->assertNotNull($first->fresh()->sha256);
        Storage::disk('social_media')->assertExists($first->path);
        Storage::disk('public')->assertMissing($first->path);
    }

    private function publicMedia(string $path): MarketingCampaignPostMedia
    {
        $contents = file_get_contents(base_path('tests/Fixtures/valid.jpg'));
        Storage::disk('public')->put($path, $contents);

        return MarketingCampaignPostMedia::factory()->create([
            'source' => 'local',
            'disk' => 'public',
            'path' => $path,
            'mime_type' => 'image/jpeg',
            'media_type' => 'image',
        ]);
    }
}
