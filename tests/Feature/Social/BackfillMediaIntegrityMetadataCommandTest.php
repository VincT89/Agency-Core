<?php

namespace Tests\Feature\Social;

use App\Models\MarketingCampaignPostMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackfillMediaIntegrityMetadataCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_dry_run_reports_but_does_not_persist_local_metadata(): void
    {
        $contents = file_get_contents(base_path('tests/Fixtures/valid.jpg'));
        Storage::disk('public')->put('legacy/image.jpg', $contents);
        $media = MarketingCampaignPostMedia::factory()->create([
            'source' => 'local',
            'disk' => 'public',
            'path' => 'legacy/image.jpg',
            'mime_type' => null,
            'source_size_bytes' => null,
            'sha256' => null,
        ]);

        $this->artisan('social:backfill-media-integrity', [
            '--media-id' => [$media->id],
        ])->assertSuccessful();

        $media->refresh();
        $this->assertNull($media->source_size_bytes);
        $this->assertNull($media->sha256);
        $this->assertNull($media->mime_type);
    }

    public function test_apply_persists_verified_local_metadata(): void
    {
        $contents = file_get_contents(base_path('tests/Fixtures/valid.jpg'));
        Storage::disk('public')->put('legacy/image.jpg', $contents);
        $media = MarketingCampaignPostMedia::factory()->create([
            'source' => 'local',
            'disk' => 'public',
            'path' => 'legacy/image.jpg',
            'mime_type' => null,
            'source_size_bytes' => null,
            'sha256' => null,
        ]);

        $this->artisan('social:backfill-media-integrity', [
            '--apply' => true,
            '--media-id' => [$media->id],
        ])->assertSuccessful();

        $media->refresh();
        $this->assertSame(strlen($contents), $media->source_size_bytes);
        $this->assertSame(hash('sha256', $contents), $media->sha256);
        $this->assertSame('image/jpeg', $media->mime_type);
    }

    public function test_apply_never_overwrites_a_detected_mismatch(): void
    {
        Storage::disk('public')->put(
            'legacy/image.jpg',
            file_get_contents(base_path('tests/Fixtures/valid.jpg'))
        );
        $media = MarketingCampaignPostMedia::factory()->create([
            'source' => 'local',
            'disk' => 'public',
            'path' => 'legacy/image.jpg',
            'sha256' => str_repeat('a', 64),
        ]);

        $this->artisan('social:backfill-media-integrity', [
            '--apply' => true,
            '--media-id' => [$media->id],
        ])->assertFailed();

        $this->assertSame(str_repeat('a', 64), $media->fresh()->sha256);
    }
}
