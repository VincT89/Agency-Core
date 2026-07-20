<?php

namespace Tests\Feature\Integrations\N8n;

use App\Domain\Social\Actions\AddMarketingCampaignPostVersionFromN8nAction;
use App\Domain\Social\DTOs\AddMarketingCampaignPostVersionData;
use App\Enums\Social\MarketingCampaignPostStatus;
use App\Models\Client;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Exception;

class ImageStagerRollbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_temporary_files_are_deleted_on_database_transaction_failure()
    {
        Http::fake([
            '*' => Http::response('fake-image-content', 200),
        ]);

        $this->mock(\App\Support\Network\HostResolver::class, function ($mock) {
            $mock->shouldReceive('resolveAndValidatePublicHost')->andReturn('example.com');
        });

        Storage::fake('local');
        Storage::fake('public');

        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::PendingN8n->value,
            'n8n_request_id' => 'req-new',
        ]);

        // Simula un errore nel database (es. unique constraint violation o timeout del lock)
        DB::shouldReceive('transaction')
            ->once()
            ->andThrow(new Exception('Database lock timeout'));

        $action = app(AddMarketingCampaignPostVersionFromN8nAction::class);

        $payload = [
            'request_id' => 'req-new',
            'regeneration_type' => 'full',
            'title' => 'Title',
            'caption' => 'Caption',
            'image_url' => 'https://example.com/img.jpg',
            'raw_payload' => [],
        ];

        $data = AddMarketingCampaignPostVersionData::fromArray($post->id, $payload);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Database lock timeout');

        try {
            $action->execute($data);
        } finally {
            // Verifica che la cartella temp sia vuota dopo il fallimento
            $this->assertEmpty(Storage::disk('local')->allFiles('temp/n8n_images'));
        }
    }
}
