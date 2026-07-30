<?php

namespace Tests\Feature\Social;

use App\Domain\Social\Enums\VersionMediaBackfillClassification;
use App\Domain\Social\Services\VersionMediaPivotBackfillAssessor;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostMedia;
use App\Models\MarketingCampaignPostVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillPostMediaPivotCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_mode_is_dry_run_and_never_writes(): void
    {
        [$version, $media] = $this->resolvableVersion('legacy/a.jpg');

        $this->artisan('social:backfill-post-media-pivot')
            ->expectsOutputToContain('DRY-RUN')
            ->assertSuccessful();

        $this->assertDatabaseMissing('marketing_campaign_post_version_media', [
            'marketing_campaign_post_version_id' => $version->id,
            'marketing_campaign_post_media_id' => $media->id,
        ]);
    }

    public function test_apply_attaches_only_the_exact_referenced_media_in_order(): void
    {
        $post = MarketingCampaignPost::factory()->create();
        $first = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'path' => 'legacy/first.jpg',
        ]);
        $unrelated = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'path' => 'legacy/unrelated.jpg',
        ]);
        $second = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'path' => 'legacy/second.jpg',
        ]);
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'image_urls' => [
                '/storage/legacy/second.jpg',
                '/storage/legacy/first.jpg',
            ],
            'image_url' => null,
            'image_path' => null,
        ]);

        $this->artisan('social:backfill-post-media-pivot', ['--apply' => true])
            ->assertSuccessful();

        $pivot = $version->fresh()
            ->mediaItems()
            ->get();

        $this->assertSame([$second->id, $first->id], $pivot->pluck('id')->all());
        $this->assertSame([0, 1], $pivot->pluck('pivot.sort_order')->all());
        $this->assertNotContains($unrelated->id, $pivot->pluck('id')->all());
    }

    public function test_apply_is_idempotent_for_already_populated_versions(): void
    {
        [$version, $media] = $this->resolvableVersion('legacy/idempotent.jpg');

        $this->artisan('social:backfill-post-media-pivot', ['--apply' => true])
            ->assertSuccessful();
        $this->artisan('social:backfill-post-media-pivot', ['--apply' => true])
            ->assertSuccessful();

        $this->assertSame(1, $version->fresh()->mediaItems()->count());
        $this->assertSame($media->id, $version->fresh()->mediaItems()->first()->id);
    }

    public function test_ambiguous_reference_is_reported_and_not_written(): void
    {
        $post = MarketingCampaignPost::factory()->create();
        MarketingCampaignPostMedia::factory()->count(2)->create([
            'marketing_campaign_post_id' => $post->id,
            'path' => 'legacy/same.jpg',
        ]);
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'image_urls' => null,
            'image_url' => null,
            'image_path' => 'legacy/same.jpg',
        ]);

        $this->artisan('social:backfill-post-media-pivot', ['--apply' => true])
            ->expectsOutputToContain('classification=ambiguous')
            ->assertFailed();

        $this->assertSame(0, $version->fresh()->mediaItems()->count());
    }

    public function test_unresolvable_version_is_reported_and_not_written(): void
    {
        $post = MarketingCampaignPost::factory()->create();
        MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'path' => 'current/only.jpg',
        ]);
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'image_urls' => null,
            'image_url' => null,
            'image_path' => null,
        ]);

        $this->artisan('social:backfill-post-media-pivot', ['--apply' => true])
            ->expectsOutputToContain('classification=unresolvable')
            ->assertFailed();

        $this->assertSame(0, $version->fresh()->mediaItems()->count());
    }

    public function test_current_version_with_one_exact_post_legacy_media_is_resolvable(): void
    {
        $post = MarketingCampaignPost::factory()->create([
            'media_path' => 'legacy/current.jpg',
        ]);
        $media = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'path' => 'legacy/current.jpg',
        ]);
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'image_urls' => null,
            'image_url' => null,
            'image_path' => null,
        ]);
        $post->update(['current_version_id' => $version->id]);

        $assessment = app(VersionMediaPivotBackfillAssessor::class)
            ->assess($version->fresh());

        $this->assertSame(
            VersionMediaBackfillClassification::DeterministicallyResolvable,
            $assessment->classification
        );
        $this->assertSame([$media->id], $assessment->mediaIds);
    }

    public function test_foreign_media_in_existing_pivot_fails_the_audit(): void
    {
        $post = MarketingCampaignPost::factory()->create();
        $otherPost = MarketingCampaignPost::factory()->create();
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
        ]);
        $foreign = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $otherPost->id,
        ]);
        $version->mediaItems()->attach($foreign->id, ['sort_order' => 0]);

        $assessment = app(VersionMediaPivotBackfillAssessor::class)
            ->assess($version->fresh());

        $this->assertSame(
            VersionMediaBackfillClassification::ForeignMedia,
            $assessment->classification
        );

        $this->artisan('social:audit-post-media-pivot')
            ->expectsOutputToContain('classification=foreign_media')
            ->assertFailed();
    }

    /**
     * @return array{MarketingCampaignPostVersion, MarketingCampaignPostMedia}
     */
    private function resolvableVersion(string $path): array
    {
        $post = MarketingCampaignPost::factory()->create();
        $media = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'path' => $path,
        ]);
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'image_urls' => null,
            'image_url' => null,
            'image_path' => $path,
        ]);

        return [$version, $media];
    }
}
