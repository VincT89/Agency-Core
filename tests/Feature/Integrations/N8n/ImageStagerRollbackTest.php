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
            '*' => Http::response(
                file_get_contents(base_path('tests/Fixtures/valid.jpg')),
                200,
                ['Content-Type' => 'image/jpeg']
            ),
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

    public function test_deleteTemporary_failure_after_commit_preserves_promoted_files()
    {
        Http::fake([
            '*' => Http::response(
                file_get_contents(base_path('tests/Fixtures/valid.jpg')),
                200,
                ['Content-Type' => 'image/jpeg']
            ),
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
            'n8n_request_id' => 'req-new-2',
        ]);

        $payload = [
            'request_id' => 'req-new-2',
            'regeneration_type' => 'full',
            'title' => 'Title',
            'caption' => 'Caption',
            'image_url' => 'https://example.com/img.jpg',
            'raw_payload' => [],
        ];

        $data = AddMarketingCampaignPostVersionData::fromArray($post->id, $payload);

        // Creiamo un anonymous class invece di Mockery per evitare problemi
        $stager = new class(app(\App\Support\Network\HostResolver::class)) extends \App\Domain\Social\Services\ImageStagerService {
            public function deleteTemporary(array $paths): void {
                throw new \Exception('Delete temporary failed');
            }
        };
        
        $this->app->instance(\App\Domain\Social\Services\ImageStagerService::class, $stager);

        $action = app(AddMarketingCampaignPostVersionFromN8nAction::class);
        $result = $action->execute($data);

        // L'azione dovrebbe aver completato con successo (l'eccezione è ignorata/loggata)
        $this->assertEquals('created', $result->outcome);
        
        // Verifichiamo che il DB sia stato commitato (la versione esiste)
        $this->assertDatabaseHas('marketing_campaign_post_versions', [
            'marketing_campaign_post_id' => $post->id,
            'n8n_request_id' => 'req-new-2',
        ]);
        
        $post->refresh();
        $this->assertNotNull($post->current_version_id);
        
        // Verifichiamo che il file promosso sia persistito su public disk
        $media = $post->currentVersion->mediaItems->first();
        $this->assertNotNull($media);
        Storage::disk('public')->assertExists($media->path);
    }
}
