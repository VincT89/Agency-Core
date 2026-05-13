<?php

namespace Tests\Feature;

use App\Enums\Social\MarketingCampaignPostStatus;
use App\Models\MarketingCampaignPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MarketingCampaignPostRegenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_regeneration_dispatches_job()
    {
        Queue::fake();

        $user = User::factory()->create();
        $post = MarketingCampaignPost::factory()->create([
            'status' => MarketingCampaignPostStatus::Generated,
        ]);

        config(['services.n8n.regenerate_social_post_webhook_url' => 'http://test.local']);

        $action = app(\App\Domain\Social\Actions\RequestMarketingCampaignPostRegenerationAction::class);
        $action->execute($post, $user, 'full');

        Queue::assertPushed(\App\Jobs\RequestMarketingCampaignPostRegenerationJob::class);
    }

    public function test_regeneration_fails_without_webhook_url()
    {
        config(['services.n8n.regenerate_social_post_webhook_url' => null]);
        config(['services.n8n.generate_social_post_webhook_url' => null]);

        $user = User::factory()->create();
        $post = MarketingCampaignPost::factory()->create([
            'status' => MarketingCampaignPostStatus::Generated,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Webhook URL per l\'evento marketing_campaign_post_regeneration non configurato');

        $action = app(\App\Domain\Social\Actions\RequestMarketingCampaignPostRegenerationAction::class);
        $action->execute($post, $user, 'full');
    }

    public function test_duplicate_regeneration_is_blocked()
    {
        $post = MarketingCampaignPost::factory()->create([
            'status' => MarketingCampaignPostStatus::Regenerating,
        ]);

        $this->assertFalse($post->canRegenerate());
    }

    public function test_regeneration_callback_creates_caption_version()
    {
        $this->markTestIncomplete('To be implemented with specific JSON payload');
    }

    public function test_regeneration_callback_creates_image_version()
    {
        $this->markTestIncomplete('To be implemented with specific JSON payload');
    }

    public function test_regeneration_callback_rejects_wrong_request_id()
    {
        $this->markTestIncomplete('To be implemented with specific JSON payload');
    }

    public function test_cancel_regeneration_restores_previous_status()
    {
        $this->markTestIncomplete('To be implemented by Livewire component testing');
    }
}
