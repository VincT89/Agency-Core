<?php

namespace Tests\Feature\Integrations\N8n;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class N8nIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.n8n.token' => 'test-token',
            'services.n8n.signing_secret' => null,
            'services.n8n.require_signature' => false,
            'services.n8n.require_idempotency_key' => true,
        ]);
        Cache::clear();

        Route::post('/testing/n8n-idempotency', function () {
            $calls = Cache::increment('testing:n8n-handler-calls');

            return response()->json([
                'calls' => $calls,
                'transaction_level' => DB::transactionLevel(),
            ], 201);
        })->middleware(['n8n.auth', 'n8n.idempotency']);
    }

    public function test_mutating_request_requires_idempotency_key(): void
    {
        $this->withToken('test-token')
            ->postJson('/testing/n8n-idempotency', ['message' => 'hello'])
            ->assertStatus(400);
    }

    public function test_completed_response_is_replayed_without_reexecuting_handler(): void
    {
        $baselineTransactionLevel = DB::transactionLevel();
        $headers = [
            'Authorization' => 'Bearer test-token',
            'Idempotency-Key' => 'message-123',
        ];

        $this->withHeaders($headers)
            ->postJson('/testing/n8n-idempotency', ['message' => 'hello'])
            ->assertStatus(201)
            ->assertJson([
                'calls' => 1,
                'transaction_level' => $baselineTransactionLevel,
            ])
            ->assertHeaderMissing('X-Idempotent-Replay');

        $this->withHeaders($headers)
            ->postJson('/testing/n8n-idempotency', ['message' => 'hello'])
            ->assertStatus(201)
            ->assertJson([
                'calls' => 1,
                'transaction_level' => $baselineTransactionLevel,
            ])
            ->assertHeader('X-Idempotent-Replay', 'true');

        $this->assertSame(1, Cache::get('testing:n8n-handler-calls'));
    }

    public function test_key_cannot_be_reused_for_different_payload(): void
    {
        $headers = [
            'Authorization' => 'Bearer test-token',
            'Idempotency-Key' => 'message-123',
        ];

        $this->withHeaders($headers)
            ->postJson('/testing/n8n-idempotency', ['message' => 'first'])
            ->assertStatus(201);

        $this->withHeaders($headers)
            ->postJson('/testing/n8n-idempotency', ['message' => 'second'])
            ->assertStatus(409);
    }

    public function test_key_cannot_be_reused_for_a_different_query(): void
    {
        $headers = [
            'Authorization' => 'Bearer test-token',
            'Idempotency-Key' => 'query-message-123',
        ];

        $this->withHeaders($headers)
            ->postJson(
                '/testing/n8n-idempotency?scope=one',
                ['message' => 'same']
            )
            ->assertStatus(201);

        $this->withHeaders($headers)
            ->postJson(
                '/testing/n8n-idempotency?scope=two',
                ['message' => 'same']
            )
            ->assertStatus(409);
    }

    public function test_invalid_short_key_is_rejected(): void
    {
        $this->withHeaders([
            'Authorization' => 'Bearer test-token',
            'Idempotency-Key' => 'short',
        ])->postJson('/testing/n8n-idempotency', ['message' => 'hello'])
            ->assertStatus(400);
    }

    public function test_stale_in_progress_reservation_can_be_recovered(): void
    {
        $key = 'stale-message-123';
        DB::table('integration_idempotency_keys')->insert([
            'provider' => 'n8n',
            'key_hash' => hash('sha256', $key),
            'request_hash' => hash(
                'sha256',
                "POST\n/testing/n8n-idempotency\n"
            ),
            'route' => 'testing/n8n-idempotency',
            'expires_at' => now()->addDay(),
            'created_at' => now()->subMinutes(31),
            'updated_at' => now()->subMinutes(31),
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer test-token',
            'Idempotency-Key' => $key,
        ])->post('/testing/n8n-idempotency')
            ->assertStatus(201)
            ->assertJson(['calls' => 1]);

        $this->assertDatabaseCount('integration_idempotency_keys', 1);
        $this->assertDatabaseHas('integration_idempotency_keys', [
            'provider' => 'n8n',
            'key_hash' => hash('sha256', $key),
            'status_code' => 201,
        ]);
    }

    public function test_stale_key_cannot_be_repurposed_for_another_request(): void
    {
        $key = 'stale-conflict-123';
        DB::table('integration_idempotency_keys')->insert([
            'provider' => 'n8n',
            'key_hash' => hash('sha256', $key),
            'request_hash' => hash(
                'sha256',
                "POST\n/testing/n8n-idempotency\n"
            ),
            'route' => 'testing/n8n-idempotency',
            'expires_at' => now()->addDay(),
            'created_at' => now()->subMinutes(31),
            'updated_at' => now()->subMinutes(31),
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer test-token',
            'Idempotency-Key' => $key,
        ])->postJson('/testing/n8n-idempotency', ['message' => 'different'])
            ->assertStatus(409);

        $this->assertNull(Cache::get('testing:n8n-handler-calls'));
        $this->assertDatabaseCount('integration_idempotency_keys', 1);
    }
}
