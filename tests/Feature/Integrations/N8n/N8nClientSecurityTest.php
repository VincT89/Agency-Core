<?php

namespace Tests\Feature\Integrations\N8n;

use App\Models\IntegrationLog;
use App\Services\Integrations\N8n\N8nClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class N8nClientSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_secret_and_response_secret_are_not_logged(): void
    {
        config([
            'services.n8n.submit_marketing_campaign_post_webhook_url' => 'https://n8n.example/webhook/secret-path?key=top-secret',
        ]);
        Http::fake([
            '*' => Http::response([
                'success' => false,
                'access_token' => 'provider-secret',
                'message' => 'bad request',
            ], 400),
        ]);

        try {
            app(N8nClient::class)->submitMarketingCampaignPost([
                'request_id' => 'request-1',
            ]);
            $this->fail('Expected the provider failure.');
        } catch (RuntimeException) {
            //
        }

        $log = IntegrationLog::query()->latest('id')->firstOrFail();
        $serialized = json_encode($log->toArray());

        $this->assertSame('https://n8n.example', $log->endpoint);
        $this->assertStringNotContainsString('secret-path', $serialized);
        $this->assertStringNotContainsString('top-secret', $serialized);
        $this->assertStringNotContainsString('provider-secret', $serialized);
        $this->assertSame(
            '[REDACTED]',
            $log->response['payload']['access_token']
        );
    }
}
