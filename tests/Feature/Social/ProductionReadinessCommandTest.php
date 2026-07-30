<?php

namespace Tests\Feature\Social;

use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\Social\MarketingCampaignStatus;
use App\Enums\Social\PublicationMode;
use App\Enums\Social\SocialApiStatus;
use App\Enums\Social\SocialPlatform;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostMedia;
use App\Models\MarketingCampaignPostVersion;
use App\Models\SystemHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionReadinessCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fails_when_production_guards_are_not_ready(): void
    {
        config([
            'app.url' => 'http://localhost',
            'queue.default' => 'sync',
            'social.publishing.dry_run' => true,
            'social.auto_publish_enabled' => false,
        ]);

        $this->artisan('social:production-readiness')
            ->expectsOutputToContain(
                'Prontezza alla produzione non confermata'
            )
            ->assertFailed();
    }

    public function test_it_succeeds_for_a_ready_environment(): void
    {
        $this->configureReadyEnvironment();
        SystemHeartbeat::create([
            'name' => 'scheduler',
            'last_seen_at' => now(),
            'metadata' => ['status' => 'ok'],
        ]);

        $this->artisan('social:production-readiness')
            ->expectsOutputToContain('Prontezza alla produzione confermata.')
            ->assertSuccessful();
    }

    public function test_it_fails_for_a_stale_scheduler_heartbeat(): void
    {
        $this->configureReadyEnvironment();
        SystemHeartbeat::create([
            'name' => 'scheduler',
            'last_seen_at' => now()->subMinutes(10),
            'metadata' => ['status' => 'stale'],
        ]);

        $this->artisan('social:production-readiness')
            ->assertFailed();
    }

    public function test_it_rejects_an_automatic_target_without_exactly_one_ready_account(): void
    {
        $this->configureReadyEnvironment();
        config([
            'services.meta.client_id' => 'meta-client',
            'services.meta.client_secret' => 'meta-secret',
        ]);
        SystemHeartbeat::create([
            'name' => 'scheduler',
            'last_seen_at' => now(),
        ]);

        $campaign = MarketingCampaign::factory()->create([
            'status' => MarketingCampaignStatus::Active,
            'publication_mode' => PublicationMode::Automatic,
            'client_review_required' => false,
        ]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Approved,
            'scheduled_date' => now()->addHour()->toDateString(),
            'scheduled_time' => now()->addHour()->format('H:i:s'),
            'publishing_platforms' => [
                SocialPlatform::Facebook->value,
            ],
        ]);
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
        ]);
        $post->update(['current_version_id' => $version->id]);

        ClientSocialAccount::factory()->create([
            'client_id' => $campaign->client_id,
            'platform' => SocialPlatform::Facebook,
            'api_status' => SocialApiStatus::Connected,
            'facebook_page_id' => 'page-not-ready',
            'publishing_capabilities' => [],
        ]);

        $this->artisan('social:production-readiness')
            ->expectsOutputToContain('account pronti=0, atteso=1')
            ->assertFailed();
    }

    public function test_it_rejects_tiktok_direct_mode_when_direct_publish_is_disabled(): void
    {
        $this->configureReadyEnvironment();
        config([
            'services.tiktok.client_key' => 'tiktok-key',
            'services.tiktok.client_secret' => 'tiktok-secret',
            'services.tiktok.delivery_mode' => 'direct',
            'services.tiktok.upload_mode' => 'PullFromUrl',
            'services.tiktok.mock_publishing' => false,
            'services.tiktok.direct_publish_enabled' => false,
        ]);
        SystemHeartbeat::create([
            'name' => 'scheduler',
            'last_seen_at' => now(),
        ]);

        $campaign = MarketingCampaign::factory()->create([
            'status' => MarketingCampaignStatus::Active,
            'publication_mode' => PublicationMode::Automatic,
            'client_review_required' => false,
        ]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Approved,
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '09:00:00',
            'publishing_platforms' => [SocialPlatform::Tiktok->value],
        ]);
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
        ]);
        $post->update(['current_version_id' => $version->id]);
        ClientSocialAccount::factory()->create([
            'client_id' => $campaign->client_id,
            'platform' => SocialPlatform::Tiktok,
            'api_status' => SocialApiStatus::Connected,
            'provider_account_id' => 'creator-123',
            'publishing_capabilities' => [
                'tiktok' => ['can_publish_video' => true],
            ],
        ]);

        $this->artisan('social:production-readiness')
            ->expectsOutputToContain(
                'direct publish TikTok non abilitato'
            )
            ->assertFailed();
    }

    public function test_it_rejects_a_ready_account_without_a_resolvable_provider_target(): void
    {
        $this->configureReadyEnvironment();
        config([
            'services.meta.client_id' => 'meta-client',
            'services.meta.client_secret' => 'meta-secret',
        ]);
        SystemHeartbeat::create([
            'name' => 'scheduler',
            'last_seen_at' => now(),
        ]);

        $campaign = MarketingCampaign::factory()->create([
            'status' => MarketingCampaignStatus::Active,
            'publication_mode' => PublicationMode::Automatic,
            'client_review_required' => false,
        ]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Approved,
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '09:00:00',
            'publishing_platforms' => [SocialPlatform::Facebook->value],
        ]);
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
        ]);
        $post->update(['current_version_id' => $version->id]);
        ClientSocialAccount::factory()->create([
            'client_id' => $campaign->client_id,
            'platform' => SocialPlatform::Facebook,
            'api_status' => SocialApiStatus::Connected,
            'provider_account_id' => null,
            'facebook_page_id' => null,
            'publishing_capabilities' => [
                'facebook' => ['enabled' => true],
            ],
        ]);

        $this->artisan('social:production-readiness')
            ->expectsOutputToContain('target provider non risolvibile')
            ->assertFailed();
    }

    public function test_it_rejects_local_social_media_on_public_disk(): void
    {
        $this->configureReadyEnvironment();
        SystemHeartbeat::create([
            'name' => 'scheduler',
            'last_seen_at' => now(),
        ]);
        MarketingCampaignPostMedia::factory()->create([
            'source' => 'local',
            'disk' => 'public',
            'path' => 'marketing/campaign-posts/exposed.jpg',
        ]);

        $this->artisan('social:production-readiness')
            ->expectsOutputToContain(
                'media social devono essere migrati sul disco privato'
            )
            ->assertFailed();
    }

    public function test_it_rejects_weak_n8n_secrets(): void
    {
        $this->configureReadyEnvironment();
        config([
            'services.n8n.token' => 'short-token',
            'services.n8n.signing_secret' => 'short-secret',
        ]);
        SystemHeartbeat::create([
            'name' => 'scheduler',
            'last_seen_at' => now(),
        ]);

        $this->artisan('social:production-readiness')
            ->expectsOutputToContain(
                'token e signing secret n8n di almeno 32 byte'
            )
            ->assertFailed();
    }

    private function configureReadyEnvironment(): void
    {
        config([
            'app.url' => 'https://agency.example.test',
            'queue.default' => 'database',
            'queue.connections.database.driver' => 'database',
            'queue.connections.database.table' => 'jobs',
            'social.publishing.dry_run' => false,
            'social.auto_publish_enabled' => true,
            'services.n8n.token' => str_repeat('t', 32),
            'services.n8n.signing_secret' => str_repeat('s', 32),
            'services.n8n.require_signature' => true,
            'services.n8n.require_idempotency_key' => true,
        ]);

        foreach (config('system-monitoring.queues', []) as $queue) {
            SystemHeartbeat::updateOrCreate(
                ['name' => 'queue:'.$queue],
                ['last_seen_at' => now(), 'metadata' => ['status' => 'ok']]
            );
        }
    }
}
