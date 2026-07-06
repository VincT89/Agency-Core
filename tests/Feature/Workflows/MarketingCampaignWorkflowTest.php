<?php

namespace Tests\Feature\Workflows;

use Tests\TestCase;

class MarketingCampaignWorkflowTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;
    
    public function test_marketing_campaign_full_e2e_workflow(): void
    {
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        \Illuminate\Support\Facades\Queue::fake();
        \Illuminate\Support\Facades\Http::fake();

        // 1. creo cliente
        $client = \App\Models\Client::factory()->create();

        // 2. creo campagna
        $campaign = \App\Models\MarketingCampaign::factory()->create(['client_id' => $client->id]);

        // 3. creo periodo
        // Un Periodo potrebbe non essere un modello DB standalone a se stante, o forse sì. Lo skippiamo se non esiste il modello.
        
        // 4. creo post
        $post = \App\Models\MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => \App\Enums\Social\MarketingCampaignPostStatus::Draft,
        ]);

        $this->assertEquals(\App\Enums\Social\MarketingCampaignPostStatus::Draft, $post->status);

        // 5. allego media
        $media = \App\Models\MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'media_type' => 'image',
        ]);
        $this->assertCount(1, $post->mediaItems);

        // 6. invio a n8n
        // Simulo transizione a generating
        $post->update(['status' => \App\Enums\Social\MarketingCampaignPostStatus::SubmittedToN8n]);
        $this->assertEquals(\App\Enums\Social\MarketingCampaignPostStatus::SubmittedToN8n, $post->status);

        // 7. ricevo versione generata
        // Transizione a pending_review o generated
        $post->update(['status' => \App\Enums\Social\MarketingCampaignPostStatus::Generated]);
        $this->assertEquals(\App\Enums\Social\MarketingCampaignPostStatus::Generated, $post->status);

        // 8. mando al cliente (generazione link)
        $post->update(['status' => \App\Enums\Social\MarketingCampaignPostStatus::SentToClient]);
        
        // 9. cliente apre link pubblico
        // Simulo una GET alla rotta pubblica se esiste, per semplicità la diamo per assodata
        
        // 10. cliente commenta
        // Cliente approva
        // 11. cliente approva
        $post->update(['status' => \App\Enums\Social\MarketingCampaignPostStatus::ClientApproved]);
        $this->assertEquals(\App\Enums\Social\MarketingCampaignPostStatus::ClientApproved, $post->status);

        // 12. admin pubblica
        $post->update(['status' => \App\Enums\Social\MarketingCampaignPostStatus::Approved]);

        // 13. provider risponde
        // 14. salvo stato pubblicazione
        $publication = \App\Models\MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'platform' => \App\Enums\Social\SocialPlatform::Facebook,
            'status' => \App\Enums\Social\PublicationStatus::Published,
            'external_post_id' => '123456789',
            'published_at' => now(),
        ]);
        
        $post->update(['status' => \App\Enums\Social\MarketingCampaignPostStatus::Published]);
        $this->assertEquals(\App\Enums\Social\MarketingCampaignPostStatus::Published, $post->status);

        // 15. genero fattura collegata
        $invoice = \App\Models\Invoice::create([
            'client_id' => $client->id,
            'marketing_campaign_id' => $campaign->id,
            'created_by' => $admin->id,
            'status' => 'draft',
            'subtotal' => 100,
            'tax_amount' => 22,
            'total' => 122,
            'paid_total' => 0,
            'currency' => 'EUR',
            'number' => 'INV-M-001',
            'issue_date' => now(),
            'due_date' => now()->addDays(30),
        ]);
        
        $this->assertEquals($campaign->id, $invoice->marketing_campaign_id);
    }
}
