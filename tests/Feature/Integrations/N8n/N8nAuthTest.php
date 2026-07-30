<?php

namespace Tests\Feature\Integrations\N8n;

use App\Models\IntegrationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class N8nAuthTest extends TestCase
{
    use RefreshDatabase;

    private const SIGNING_SECRET = 'test-signing-secret-32-bytes-long';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.n8n.token', 'test_secret_token');
        Config::set('services.n8n.signing_secret', null);
        Config::set('services.n8n.require_signature', false);
        Cache::clear();
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

    public function test_signed_request_is_accepted_and_replay_is_rejected(): void
    {
        Config::set('services.n8n.signing_secret', self::SIGNING_SECRET);
        $timestamp = (string) now()->timestamp;
        $signature = $this->signature(
            $timestamp,
            'GET',
            '/api/v1/integrations/n8n/health'
        );
        $headers = [
            'Authorization' => 'Bearer test_secret_token',
            'X-N8N-Timestamp' => $timestamp,
            'X-N8N-Signature' => 'sha256='.$signature,
        ];

        $this->withHeaders($headers)
            ->get('/api/v1/integrations/n8n/health')
            ->assertOk();

        $this->withHeaders($headers)
            ->get('/api/v1/integrations/n8n/health')
            ->assertStatus(409);
    }

    public function test_signed_request_rejects_tampering_and_expired_timestamp(): void
    {
        Config::set('services.n8n.signing_secret', self::SIGNING_SECRET);
        $timestamp = (string) now()->timestamp;

        $this->withHeaders([
            'Authorization' => 'Bearer test_secret_token',
            'X-N8N-Timestamp' => $timestamp,
            'X-N8N-Signature' => str_repeat('a', 64),
        ])->getJson('/api/v1/integrations/n8n/health')
            ->assertForbidden();

        $expiredTimestamp = (string) now()->subMinutes(10)->timestamp;
        $expiredSignature = $this->signature(
            $expiredTimestamp,
            'GET',
            '/api/v1/integrations/n8n/health'
        );

        $this->withHeaders([
            'Authorization' => 'Bearer test_secret_token',
            'X-N8N-Timestamp' => $expiredTimestamp,
            'X-N8N-Signature' => $expiredSignature,
        ])->getJson('/api/v1/integrations/n8n/health')
            ->assertForbidden();
    }

    public function test_signature_is_bound_to_request_target_and_query(): void
    {
        Config::set('services.n8n.signing_secret', self::SIGNING_SECRET);
        Route::get('/testing/n8n-signed', fn () => response()->json([
            'ok' => true,
        ]))->middleware('n8n.auth');
        $timestamp = (string) now()->timestamp;
        $signature = $this->signature(
            $timestamp,
            'GET',
            '/testing/n8n-signed?scope=one'
        );

        $this->withHeaders([
            'Authorization' => 'Bearer test_secret_token',
            'X-N8N-Timestamp' => $timestamp,
            'X-N8N-Signature' => $signature,
        ])->getJson('/testing/n8n-signed?scope=two')
            ->assertForbidden();
    }

    public function test_production_configuration_fails_closed_without_signing_secret(): void
    {
        $token = str_repeat('t', 32);
        Config::set('services.n8n.token', $token);
        Config::set('services.n8n.signing_secret', null);
        Config::set('services.n8n.require_signature', true);

        $this->withToken($token)
            ->getJson('/api/v1/integrations/n8n/health')
            ->assertStatus(503);
    }

    public function test_required_signature_fails_closed_with_weak_bearer_secret(): void
    {
        Config::set('services.n8n.signing_secret', self::SIGNING_SECRET);
        Config::set('services.n8n.require_signature', true);

        $this->withToken('test_secret_token')
            ->getJson('/api/v1/integrations/n8n/health')
            ->assertStatus(503);
    }

    public function test_short_signing_secret_fails_closed(): void
    {
        Config::set('services.n8n.signing_secret', 'short-secret');

        $this->withToken('test_secret_token')
            ->getJson('/api/v1/integrations/n8n/health')
            ->assertStatus(503);
    }

    private function signature(
        string $timestamp,
        string $method,
        string $requestTarget,
        string $body = ''
    ): string {
        return hash_hmac(
            'sha256',
            implode("\n", [
                $timestamp,
                strtoupper($method),
                $requestTarget,
                $body,
            ]),
            self::SIGNING_SECRET
        );
    }
}
