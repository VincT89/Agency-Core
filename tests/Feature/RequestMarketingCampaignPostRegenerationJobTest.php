<?php

namespace Tests\Feature;

use App\Enums\Social\MarketingCampaignPostStatus;
use App\Jobs\RequestMarketingCampaignPostRegenerationJob;
use App\Models\MarketingCampaignPost;
use App\Models\User;
use App\Services\Integrations\N8n\N8nClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;
use Exception;

class RequestMarketingCampaignPostRegenerationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_can_be_dispatched()
    {
        Queue::fake();

        $post = new MarketingCampaignPost();
        $post->id = 1;
        $post->status = MarketingCampaignPostStatus::Regenerating;

        $payload = ['dummy' => 'data'];

        RequestMarketingCampaignPostRegenerationJob::dispatch($post, $payload, MarketingCampaignPostStatus::Draft->value);

        Queue::assertPushed(RequestMarketingCampaignPostRegenerationJob::class, function ($job) use ($post, $payload) {
            return $job->post->id === $post->id &&
                   $job->payload === $payload &&
                   $job->previousStatus === MarketingCampaignPostStatus::Draft->value;
        });
    }

    public function test_job_restores_previous_status_on_failure()
    {
        $post = Mockery::mock(MarketingCampaignPost::class)->makePartial();
        $post->shouldReceive('refresh')->once();
        $post->shouldReceive('update')->once()->with([
            'status' => MarketingCampaignPostStatus::Generated->value,
            'n8n_error' => 'Rigenerazione fallita dopo 3 tentativi: N8n is down',
        ]);

        $payload = ['dummy' => 'data'];
        $previousStatus = MarketingCampaignPostStatus::Generated->value;

        $job = new RequestMarketingCampaignPostRegenerationJob($post, $payload, $previousStatus);
        
        $exception = new Exception('N8n is down');
        $job->failed($exception);
        
        $this->assertTrue(true); // Se arriviamo qui, i mock hanno verificato correttamente
    }
}
