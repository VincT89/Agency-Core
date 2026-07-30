<?php

namespace Tests\Feature\Social;

use App\Domain\Social\DTOs\MarketingCampaignPostPublicationSnapshot;
use App\Domain\Social\Services\CanonicalJsonEncoder;
use App\Domain\Social\Services\MarketingCampaignPostPublicationIntegrityVerifier;
use App\Models\MarketingCampaignPostPublication;
use App\Enums\Social\SocialPlatform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MarketingCampaignPostPublicationIntegrityVerifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_integrity_passes_with_same_canonical_snapshot()
    {
        Storage::fake('public');
        Storage::disk('public')->put('campaigns/a.png', 'test-content');
        $fileHash = md5_file(Storage::disk('public')->path('campaigns/a.png'));

        $snapshot = new MarketingCampaignPostPublicationSnapshot(
            post_id: 1,
            version_id: 2,
            version_number: 1,
            provider: 'meta',
            platform: SocialPlatform::Facebook,
            social_account_id: 10,
            account_external_id: '123',
            page_id: null,
            profile_id: null,
            privacy_options: [],
            publication_type: 'publish',
            content_type: 'feed',
            title: 'Test',
            caption: 'Test caption',
            hashtags: [],
            media: [
                [
                    'media_id' => 123,
                    'storage_source' => 'local', 'size_bytes' => 12, 'mime_type' => 'image/png', 'media_type' => 'image', 'sha256' => hash('sha256', 'test-content'), 'sort_order' => 1,
                    'disk' => 'public',
                    'path' => 'campaigns/a.png',
                    'checksum' => $fileHash
                ]
            ],
            scheduled_date: null,
            scheduled_time: null,
            platform_options: [],
            schema_version: 1
        );

        $encoder = new CanonicalJsonEncoder();
        $hash = hash('sha256', $encoder->encode($snapshot));
        
        $payload = json_decode($encoder->encode($snapshot), true);

        $publication = MarketingCampaignPostPublication::factory()->make([
            'marketing_campaign_post_id' => 1,
            'marketing_campaign_post_version_id' => 2,
            'client_social_account_id' => 10,
            'platform' => \App\Enums\Social\SocialPlatform::Facebook,
            'snapshot_schema_version' => 1,
            'snapshot_hash' => $hash,
            'payload_snapshot' => $payload,
        ]);

        $verifier = app(MarketingCampaignPostPublicationIntegrityVerifier::class);
        $result = $verifier->verify($publication);
        if (!$result->passed) {
            var_dump($result->errors);
        }
        $this->assertTrue($result->passed);
    }

    public function test_integrity_fails_when_snapshot_hash_changes()
    {
        $payload = [
            'post_id' => 1,
            'version_id' => 2,
            'version_number' => 1,
            'provider' => 'meta',
            'platform' => 'facebook',
            'target' => [
                'social_account_id' => 10,
                'external_id' => '123',
                'page_id' => null,
                'profile_id' => null,
                'privacy_options' => [], 'publication_type' => 'post'
            ],
            'publication_type' => 'publish',
            'content_type' => 'feed',
            'title' => 'Test',
            'caption' => 'Test',
            'hashtags' => [],
            'media' => [
                [
                    'media_id' => 123,
                    'storage_source' => 'local',
                    'size_bytes' => 12,
                    'mime_type' => 'image/png',
                    'media_type' => 'image',
                    'sha256' => hash('sha256', 'test-content'),
                    'sort_order' => 1,
                    'disk' => 'public',
                    'path' => 'campaigns/a.png',
                    'checksum' => 'testhash'
                ]
            ],
            'scheduled_date' => null,
            'scheduled_time' => null,
            'platform_options' => [],
            'schema_version' => 1
        ];

        $publication = MarketingCampaignPostPublication::factory()->make([
            'marketing_campaign_post_id' => 1,
            'marketing_campaign_post_version_id' => 2,
            'client_social_account_id' => 10,
            'platform' => \App\Enums\Social\SocialPlatform::Facebook,
            'snapshot_schema_version' => 1,
            'snapshot_hash' => str_repeat('0', 64),
            'payload_snapshot' => $payload,
        ]);

        $encoder = new CanonicalJsonEncoder();
        $verifier = app(MarketingCampaignPostPublicationIntegrityVerifier::class);
        $result = $verifier->verify($publication);

        $this->assertFalse($result->passed);
        $this->assertContains('Snapshot hash mismatch', $result->errors);
    }

    public function test_missing_media_returns_manual_review()
    {
        Storage::fake('public');

        $snapshot = new MarketingCampaignPostPublicationSnapshot(
            post_id: 1,
            version_id: 2,
            version_number: 1,
            provider: 'meta',
            platform: SocialPlatform::Facebook,
            social_account_id: 10,
            account_external_id: '123',
            page_id: null,
            profile_id: null,
            privacy_options: [],
            publication_type: 'publish',
            content_type: 'feed',
            title: 'Test',
            caption: 'Test caption',
            hashtags: [],
            media: [
                [
                    'media_id' => 123,
                    'storage_source' => 'local', 'size_bytes' => 12, 'mime_type' => 'image/png', 'media_type' => 'image', 'sha256' => hash('sha256', 'test-content'), 'sort_order' => 1,
                    'disk' => 'public',
                    'path' => 'campaigns/missing.png',
                    'checksum' => 'fakehash'
                ]
            ],
            scheduled_date: null,
            scheduled_time: null,
            platform_options: [],
            schema_version: 1
        );

        $encoder = new CanonicalJsonEncoder();
        $hash = hash('sha256', $encoder->encode($snapshot));
        $payload = json_decode($encoder->encode($snapshot), true);

        $publication = MarketingCampaignPostPublication::factory()->make([
            'marketing_campaign_post_id' => 1,
            'marketing_campaign_post_version_id' => 2,
            'client_social_account_id' => 10,
            'platform' => \App\Enums\Social\SocialPlatform::Facebook,
            'snapshot_schema_version' => 1,
            'snapshot_hash' => $hash,
            'payload_snapshot' => $payload,
        ]);

        $verifier = app(MarketingCampaignPostPublicationIntegrityVerifier::class);
        $result = $verifier->verify($publication);

        $this->assertFalse($result->passed);
        $this->assertEquals(\App\Enums\Social\IntegritySeverity::Error, $result->severity); // Manual review required
        $this->assertContains('Media file missing on disk: public::campaigns/missing.png', $result->errors);
    }

    public function test_provider_temporary_failure_is_retryable()
    {
        // This test simulates a temporary disk unreachable error by passing a bad disk name
        // that throws exception when checking existence
        
        $snapshot = new MarketingCampaignPostPublicationSnapshot(
            post_id: 1,
            version_id: 2,
            version_number: 1,
            provider: 'meta',
            platform: SocialPlatform::Facebook,
            social_account_id: 10,
            account_external_id: '123',
            page_id: null,
            profile_id: null,
            privacy_options: [],
            publication_type: 'publish',
            content_type: 'feed',
            title: 'Test',
            caption: 'Test caption',
            hashtags: [],
            media: [
                [
                    'media_id' => 123,
                    'storage_source' => 'local', 'size_bytes' => 12, 'mime_type' => 'image/png', 'media_type' => 'image', 'sha256' => hash('sha256', 'test-content'), 'sort_order' => 1,
                    'disk' => 'unreachable_disk_fake', // Invalid disk will throw an exception
                    'path' => 'campaigns/a.png',
                    'checksum' => 'fake'
                ]
            ],
            scheduled_date: null,
            scheduled_time: null,
            platform_options: [],
            schema_version: 1
        );

        $encoder = new CanonicalJsonEncoder();
        $hash = hash('sha256', $encoder->encode($snapshot));
        $payload = json_decode($encoder->encode($snapshot), true);

        $publication = MarketingCampaignPostPublication::factory()->make([
            'marketing_campaign_post_id' => 1,
            'marketing_campaign_post_version_id' => 2,
            'client_social_account_id' => 10,
            'platform' => \App\Enums\Social\SocialPlatform::Facebook,
            'snapshot_schema_version' => 1,
            'snapshot_hash' => $hash,
            'payload_snapshot' => $payload,
        ]);

        $verifier = app(MarketingCampaignPostPublicationIntegrityVerifier::class);
        $result = $verifier->verify($publication);

        $this->assertFalse($result->passed);
        $this->assertEquals(\App\Enums\Social\IntegritySeverity::Temporary, $result->severity); // Retryable
        $this->assertTrue($result->retryable);
        // Note: the exact exception string will be tested
        $this->assertStringContainsString('Media file verification failed', $result->errors[0]);
    }
}
