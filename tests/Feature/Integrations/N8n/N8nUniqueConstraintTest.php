<?php

namespace Tests\Feature\Integrations\N8n;

use App\Domain\Social\Actions\AddMarketingCampaignPostVersionFromN8nAction;
use App\Domain\Social\DTOs\AddMarketingCampaignPostVersionData;
use App\Enums\Social\MarketingCampaignPostStatus;
use App\Models\Client;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostVersion;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use PDOException;

class N8nUniqueConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_unique_constraint_violation_on_same_post_returns_duplicate()
    {
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::PendingN8n->value,
            'n8n_request_id' => 'req-123',
        ]);

        // Creiamo la versione già esistente
        MarketingCampaignPostVersion::create([
            'marketing_campaign_post_id' => $post->id,
            'version_number' => 1,
            'n8n_request_id' => 'req-123',
            'source' => 'n8n',
        ]);

        // Mock the transaction to throw exception with specific string
        DB::shouldReceive('transaction')
            ->once()
            ->andThrow(new UniqueConstraintViolationException(
                'mysql',
                'insert into ...',
                [],
                new PDOException("SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'req-123' for key 'mcpv_n8n_request_unique'")
            ));

        $action = app(AddMarketingCampaignPostVersionFromN8nAction::class);

        // Simuliamo una chiamata concorrente passandogli direttamente l'DTO per evitare fastCheckDuplicate,
        // Wait, fastCheckDuplicate lo fermerebbe prima di DB::transaction.
        // Possiamo fare il mock di fastCheckDuplicate o svuotare la colonna per eluderlo? No, 
        // fastCheckDuplicate fa una query reale, troverebbe la riga e bloccherebbe prima del mock DB::transaction.
        // Dobbiamo usare il mock parziale sull'action per testare solo la catch del DB.

        $mockAction = \Mockery::mock(AddMarketingCampaignPostVersionFromN8nAction::class . '[fastCheckDuplicate]', [app(\App\Domain\Social\Services\ImageStagerService::class)]);
        $mockAction->shouldAllowMockingProtectedMethods();
        $mockAction->shouldReceive('fastCheckDuplicate')->once()->andReturn(null);

        $data = AddMarketingCampaignPostVersionData::fromArray($post->id, [
            'request_id' => 'req-123',
            'regeneration_type' => 'full',
        ]);

        $result = $mockAction->execute($data);

        $this->assertEquals('duplicate', $result->outcome);
        $this->assertEquals('request_already_processed', $result->reason);
    }

    public function test_unique_constraint_violation_on_different_post_returns_conflict()
    {
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        
        $post1 = MarketingCampaignPost::factory()->create(['marketing_campaign_id' => $campaign->id]);
        $post2 = MarketingCampaignPost::factory()->create(['marketing_campaign_id' => $campaign->id]);

        // Creiamo la versione già esistente su post1
        MarketingCampaignPostVersion::create([
            'marketing_campaign_post_id' => $post1->id,
            'version_number' => 1,
            'n8n_request_id' => 'req-shared',
            'source' => 'n8n',
        ]);

        // Mock the transaction to throw exception
        DB::shouldReceive('transaction')
            ->once()
            ->andThrow(new UniqueConstraintViolationException(
                'mysql',
                'insert into ...',
                [],
                new PDOException("SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'req-shared' for key 'mcpv_n8n_request_unique'")
            ));

        $mockAction = \Mockery::mock(AddMarketingCampaignPostVersionFromN8nAction::class . '[fastCheckDuplicate]', [app(\App\Domain\Social\Services\ImageStagerService::class)]);
        $mockAction->shouldAllowMockingProtectedMethods();
        $mockAction->shouldReceive('fastCheckDuplicate')->once()->andReturn(null);

        // Chiamata su post2
        $data = AddMarketingCampaignPostVersionData::fromArray($post2->id, [
            'request_id' => 'req-shared',
            'regeneration_type' => 'full',
        ]);

        $result = $mockAction->execute($data);

        $this->assertEquals('conflict', $result->outcome);
        $this->assertEquals('request_id_used_by_another_post', $result->reason);
    }

    public function test_unrecognized_unique_constraint_violation_is_rethrown()
    {
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create(['marketing_campaign_id' => $campaign->id]);

        DB::shouldReceive('transaction')
            ->once()
            ->andThrow(new UniqueConstraintViolationException(
                'mysql',
                'insert into ...',
                [],
                new PDOException("SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '1-1' for key 'mcpv_post_version_unique'") // NON mcpv_n8n_request_unique
            ));

        $mockAction = \Mockery::mock(AddMarketingCampaignPostVersionFromN8nAction::class . '[fastCheckDuplicate]', [app(\App\Domain\Social\Services\ImageStagerService::class)]);
        $mockAction->shouldAllowMockingProtectedMethods();
        $mockAction->shouldReceive('fastCheckDuplicate')->once()->andReturn(null);

        $data = AddMarketingCampaignPostVersionData::fromArray($post->id, [
            'request_id' => 'req-123',
            'regeneration_type' => 'full',
        ]);

        $this->expectException(UniqueConstraintViolationException::class);
        $mockAction->execute($data);
    }
}
