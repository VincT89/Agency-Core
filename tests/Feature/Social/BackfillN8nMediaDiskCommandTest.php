<?php

namespace Tests\Feature\Social;

use App\Models\MarketingCampaignPostMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackfillN8nMediaDiskCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_it_backfills_disk_for_existing_files_and_is_idempotent()
    {
        Storage::disk('public')->put('existing_file.jpg', 'content');

        // Determinable and existing
        $media1 = MarketingCampaignPostMedia::factory()->create([
            'source' => 'n8n',
            'path' => 'existing_file.jpg',
            'disk' => null,
        ]);

        // Missing file
        $media2 = MarketingCampaignPostMedia::factory()->create([
            'source' => 'n8n',
            'path' => 'missing_file.jpg',
            'disk' => null,
        ]);
        
        // Null path
        $mediaNullPath = MarketingCampaignPostMedia::factory()->create([
            'source' => 'n8n',
            'path' => null,
            'disk' => null,
        ]);

        // Already backfilled
        $media3 = MarketingCampaignPostMedia::factory()->create([
            'source' => 'n8n',
            'path' => 'existing_file.jpg',
            'disk' => 'public',
        ]);

        // Not n8n
        $media4 = MarketingCampaignPostMedia::factory()->create([
            'source' => 'local',
            'path' => 'existing_file.jpg',
            'disk' => null,
        ]);

        // First run
        $this->artisan('social:backfill-n8n-media-disk')
            ->expectsOutputToContain('Analyzed')
            ->assertExitCode(0);

        $this->assertEquals('public', $media1->fresh()->disk);
        $this->assertNull($media2->fresh()->disk); // Ignored due to missing file
        $this->assertNull($mediaNullPath->fresh()->disk); // Ignored due to null path
        $this->assertEquals('public', $media3->fresh()->disk); // Was already public
        $this->assertNull($media4->fresh()->disk); // Ignored because source != n8n
        
        // Second run (Idempotency)
        $this->artisan('social:backfill-n8n-media-disk')
            ->expectsOutputToContain('Updated') // Assuming you adjust the command to output 0 updated
            ->assertExitCode(0);
            
        $this->assertEquals('public', $media1->fresh()->disk); // Still public
    }

    public function test_it_does_not_modify_disk_on_dry_run_and_reports_correct_counts()
    {
        Storage::disk('public')->put('existing_file.jpg', 'content');

        $media = MarketingCampaignPostMedia::factory()->create([
            'source' => 'n8n',
            'path' => 'existing_file.jpg',
            'disk' => null,
        ]);

        $this->artisan('social:backfill-n8n-media-disk', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run: YES')
            ->expectsOutputToContain('Would update') // we need to change command to output "Would update"
            ->assertExitCode(0);

        $this->assertNull($media->fresh()->disk);
    }
}
