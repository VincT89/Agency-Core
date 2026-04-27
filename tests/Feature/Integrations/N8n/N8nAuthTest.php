<?php

namespace Tests\Feature\Integrations\N8n;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use App\Models\IntegrationLog;

class N8nAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.n8n.token', 'test_secret_token');
    }

    public function test_health_endpoint_requires_token(): void
    {
        $response = $this->getJson('/api/v1/integrations/n8n/health');

        $response->assertStatus(401)
                 ->assertJson([
                     'ok' => false,
                     'message' => 'Unauthorized: Token missing',
                 ]);
    }

    public function test_health_endpoint_rejects_invalid_token(): void
    {
        $response = $this->withToken('wrong_token')->getJson('/api/v1/integrations/n8n/health');

        $response->assertStatus(403)
                 ->assertJson([
                     'ok' => false,
                     'message' => 'Forbidden: Invalid token',
                 ]);
    }

    public function test_health_endpoint_accepts_valid_token(): void
    {
        $response = $this->withToken('test_secret_token')->getJson('/api/v1/integrations/n8n/health');

        $response->assertStatus(200)
                 ->assertJson([
                     'ok' => true,
                     'provider' => 'n8n',
                     'status' => 'ready',
                 ]);
    }

    public function test_integration_log_model_can_store_payload_and_response(): void
    {
        $payload = ['foo' => 'bar'];
        $responsePayload = ['success' => true];

        $log = IntegrationLog::create([
            'provider' => 'n8n',
            'direction' => 'outbound',
            'payload' => $payload,
            'response' => $responsePayload,
            'status' => 'processed',
        ]);

        $this->assertIsArray($log->payload);
        $this->assertIsArray($log->response);
        $this->assertEquals('bar', $log->payload['foo']);
        $this->assertTrue($log->response['success']);
    }
}
