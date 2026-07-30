<?php

namespace Tests\Feature\Social;

use App\Domain\Social\Services\CanonicalJsonEncoder;
use App\Enums\Social\PublicationStatus;
use App\Enums\Social\SocialPlatform;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostMedia;
use App\Models\MarketingCampaignPostPublication;
use App\Models\MarketingCampaignPostVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditHistoricalIntegrityCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_clean_snapshot_history_passes(): void
    {
        $this->createValidPublication();

        $this->artisan('social:audit-historical-integrity')
            ->assertSuccessful();
    }

    public function test_snapshot_tampering_is_reported_without_mutating_the_record(): void
    {
        $publication = $this->createValidPublication();
        DB::table('marketing_campaign_post_publications')
            ->where('id', $publication->id)
            ->update(['payload_snapshot' => json_encode(['tampered' => true])]);

        $this->artisan('social:audit-historical-integrity', [
            '--publication-id' => [$publication->id],
        ])
            ->expectsOutputToContain('snapshot_hash_mismatch')
            ->assertFailed();

        $this->assertSame(
            ['tampered' => true],
            $publication->fresh()->payload_snapshot
        );
    }

    public function test_a_foreign_media_pivot_is_reported(): void
    {
        $post = MarketingCampaignPost::factory()->create();
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
        ]);
        $foreignMedia = MarketingCampaignPostMedia::factory()->create();
        $version->mediaItems()->attach($foreignMedia->id, ['sort_order' => 0]);

        $this->artisan('social:audit-historical-integrity', [
            '--post-id' => [$post->id],
        ])
            ->expectsOutputToContain('foreign_media_pivot')
            ->assertFailed();
    }

    public function test_publication_scope_does_not_audit_unrelated_post_structures(): void
    {
        $publication = $this->createValidPublication();
        $unrelatedPost = MarketingCampaignPost::factory()->create();
        $unrelatedVersion = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $unrelatedPost->id,
        ]);
        $foreignMedia = MarketingCampaignPostMedia::factory()->create();
        $unrelatedVersion->mediaItems()->attach(
            $foreignMedia->id,
            ['sort_order' => 0]
        );

        $this->artisan('social:audit-historical-integrity', [
            '--publication-id' => [$publication->id],
        ])->assertSuccessful();
    }

    public function test_malformed_snapshot_media_is_reported_without_crashing_the_audit(): void
    {
        $publication = $this->createValidPublication();
        $snapshot = $publication->payload_snapshot;
        $snapshot['media'] = ['corrupt-descriptor'];
        DB::table('marketing_campaign_post_publications')
            ->where('id', $publication->id)
            ->update(['payload_snapshot' => json_encode($snapshot)]);

        $this->artisan('social:audit-historical-integrity', [
            '--publication-id' => [$publication->id],
        ])
            ->expectsOutputToContain('invalid_snapshot_media')
            ->assertFailed();
    }

    private function createValidPublication(): MarketingCampaignPostPublication
    {
        $post = MarketingCampaignPost::factory()->create();
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
        ]);
        $post->update(['current_version_id' => $version->id]);
        $account = ClientSocialAccount::factory()->create([
            'client_id' => $post->campaign->client_id,
            'platform' => SocialPlatform::Facebook,
        ]);
        $snapshot = [
            'post_id' => $post->id,
            'version_id' => $version->id,
            'version_number' => $version->version_number,
            'provider' => 'meta',
            'platform' => SocialPlatform::Facebook->value,
            'target' => [
                'social_account_id' => $account->id,
                'external_id' => 'page-123',
                'page_id' => 'page-123',
                'profile_id' => null,
                'privacy_options' => [],
                'publication_type' => 'publish',
            ],
            'content_type' => 'post',
            'title' => 'Title',
            'caption' => 'Caption',
            'hashtags' => [],
            'media' => [],
            'scheduled_date' => null,
            'scheduled_time' => null,
            'platform_options' => [],
            'schema_version' => 1,
        ];
        $snapshotHash = hash(
            'sha256',
            app(CanonicalJsonEncoder::class)->encode($snapshot)
        );
        $idempotencyKey = hash('sha256', implode('|', [
            1,
            $version->id,
            SocialPlatform::Facebook->value,
            $account->id,
            $snapshotHash,
        ]));

        return MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'marketing_campaign_post_version_id' => $version->id,
            'client_social_account_id' => $account->id,
            'platform' => SocialPlatform::Facebook,
            'status' => PublicationStatus::Pending,
            'snapshot_schema_version' => 1,
            'snapshot_hash' => $snapshotHash,
            'idempotency_key' => $idempotencyKey,
            'payload_snapshot' => $snapshot,
            'attempt_count' => 1,
            'correlation_id' => 'audit-correlation',
        ]);
    }
}
