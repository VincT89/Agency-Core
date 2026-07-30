<?php

namespace Tests\Feature\Social;

use App\Domain\Social\Publishing\MetaPublisher;
use App\Domain\Social\Services\PublicationMediaDeliveryService;
use App\Enums\Social\PublicationFailureClassification;
use App\Enums\Social\SocialPlatform;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaignPostPublication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Mockery;

class PublisherHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_meta_publisher_classifies_rate_limits_as_temporary()
    {
        \Illuminate\Support\Facades\Config::set('social.publishing.dry_run', false);
        
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Rate limit exceeded']], 429),
        ]);

        $deliveryService = Mockery::mock(PublicationMediaDeliveryService::class);
        $publisher = new MetaPublisher($deliveryService);

        $account = ClientSocialAccount::factory()->create([
            'platform' => SocialPlatform::Facebook->value,
            'access_token' => 'valid_token',
            'provider_account_id' => '123456789',
        ]);

        $publication = MarketingCampaignPostPublication::factory()->create([
            'payload_snapshot' => [
                'target' => [
                    'external_id' => '123456789',
                    'publication_type' => 'feed',
                ],
                'caption' => 'Test',
                'hashtags' => [],
                'media' => []
            ]
        ]);

        $result = $publisher->publish($publication, $account);

        $this->assertFalse($result->success);
        $this->assertEquals(PublicationFailureClassification::Temporary, $result->failureClassification);
    }

    public function test_meta_publisher_classifies_auth_errors_as_manual_review()
    {
        \Illuminate\Support\Facades\Config::set('social.publishing.dry_run', false);
        
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token']], 401),
        ]);

        $deliveryService = Mockery::mock(PublicationMediaDeliveryService::class);
        $publisher = new MetaPublisher($deliveryService);

        $account = ClientSocialAccount::factory()->create([
            'platform' => SocialPlatform::Facebook->value,
            'access_token' => 'invalid_token',
            'provider_account_id' => '123456789',
        ]);

        $publication = MarketingCampaignPostPublication::factory()->create([
            'payload_snapshot' => [
                'target' => [
                    'external_id' => '123456789',
                    'publication_type' => 'feed',
                ],
                'caption' => 'Test',
                'hashtags' => [],
                'media' => []
            ]
        ]);

        $result = $publisher->publish($publication, $account);

        $this->assertFalse($result->success);
        $this->assertEquals(PublicationFailureClassification::ManualReview, $result->failureClassification);
    }
}
