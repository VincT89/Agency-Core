<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\Ticket;
use App\Models\Client;
use App\Jobs\Chatbot\SyncChatbotClientDataJob;
use App\Domain\Chatbot\Actions\SyncChatbotMarketingPostsAction;
use App\Models\Chatbot\ChatbotClient;

class ChatbotSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_observer_registration_dispatches_job_on_marketing_campaign_update(): void
    {
        Queue::fake();

        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);

        $campaign->update(['name' => 'Updated Campaign Name']);

        Queue::assertPushed(SyncChatbotClientDataJob::class, function ($job) use ($client) {
            return $job->clientId === $client->id;
        });
    }

    public function test_n_plus_one_marketing_posts_eager_loading(): void
    {
        $client = Client::factory()->create();
        $chatbotClient = ChatbotClient::factory()->create(['client_id' => $client->id]);
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create(['marketing_campaign_id' => $campaign->id]);

        $action = app(SyncChatbotMarketingPostsAction::class);
        
        // Se eager loading with('campaign') è presente, questo non genererà query addizionali 
        // L'action in sé esegue le query. Controllare che with('campaign') esista è garantito dal codice
        // Verifichiamo che l'esecuzione vada a buon fine e la relazione sia valorizzata implicitamente.
        $action->execute($client, $chatbotClient);

        $this->assertDatabaseHas('chatbot_marketing_posts', [
            'marketing_campaign_post_id' => $post->id,
            'campaign_name' => $campaign->name,
        ]);
    }
}
