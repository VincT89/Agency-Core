<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencySocialDomainTest extends TestCase
{
    use RefreshDatabase;
    public function test_publication_status_enum_values(): void
    {
        $this->assertEquals('pending', \App\Enums\Social\PublicationStatus::Pending->value);
        $this->assertEquals('needs_manual_review', \App\Enums\Social\PublicationStatus::NeedsManualReview->value);
        $this->assertEquals('publishing', \App\Enums\Social\PublicationStatus::Publishing->value);
    }

    public function test_instagram_container_status_result_dto(): void
    {
        $result = new \App\Domain\Social\Services\InstagramContainerStatusResult(
            status: 'ERROR',
            isPermanentError: true,
            errorMessage: 'Test error',
            responseData: []
        );

        $this->assertTrue($result->isError());
        $this->assertFalse($result->isFinished());
        $this->assertFalse($result->isTemporary());
    }

    public function test_instagram_container_status_result_dto_temporary(): void
    {
        $result = new \App\Domain\Social\Services\InstagramContainerStatusResult(
            status: 'UNKNOWN',
            isPermanentError: false,
            errorMessage: 'Test temp',
            responseData: []
        );

        $this->assertFalse($result->isError());
        $this->assertTrue($result->isTemporary());
    }

    public function test_sync_action_rules_on_db(): void
    {
        $client = \App\Models\Client::factory()->create();
        $campaign = \App\Models\MarketingCampaign::create([
            'client_id' => $client->id,
            'name' => 'Test Campaign',
            'status' => 'active'
        ]);
        $post = \App\Models\MarketingCampaignPost::create([
            'marketing_campaign_id' => $campaign->id,
            'status' => \App\Enums\Social\MarketingCampaignPostStatus::Approved
        ]);
        
        $pub1 = \App\Models\MarketingCampaignPostPublication::create([
            'marketing_campaign_post_id' => $post->id,
            'platform' => 'facebook',
            'status' => \App\Enums\Social\PublicationStatus::Failed->value,
            'correlation_id' => '123'
        ]);
        
        $pub2 = \App\Models\MarketingCampaignPostPublication::create([
            'marketing_campaign_post_id' => $post->id,
            'platform' => 'instagram',
            'status' => \App\Enums\Social\PublicationStatus::NeedsManualReview->value,
            'correlation_id' => '123'
        ]);

        $action = new \App\Domain\Social\Actions\SyncMarketingCampaignPostPublicationStatusAction();
        $action->execute($post);

        $this->assertEquals(\App\Enums\Social\MarketingCampaignPostStatus::Failed->value, $post->refresh()->status->value);

        // Ora forziamo manual review a published
        $pub2->update(['status' => \App\Enums\Social\PublicationStatus::Published->value]);
        $post->unsetRelation('publications');
        $action->execute($post);
        
        $this->assertEquals(\App\Enums\Social\MarketingCampaignPostStatus::PartialSuccess->value, $post->refresh()->status->value);
        
        // Entrambi Published
        $pub1->update(['status' => \App\Enums\Social\PublicationStatus::Published->value]);
        $post->unsetRelation('publications');
        $action->execute($post);
        
        $this->assertEquals(\App\Enums\Social\MarketingCampaignPostStatus::Published->value, $post->refresh()->status->value);
    }
}
