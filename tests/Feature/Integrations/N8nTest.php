<?php

namespace Tests\Feature\Integrations;

use Tests\Concerns\SignsN8nRequests;
use Tests\TestCase;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;

class N8nTest extends TestCase
{
    use RefreshDatabase;
    use SignsN8nRequests;

    // --- Auth Middleware ---
    public function test_n8n_health_endpoint_returns_401_without_token(): void
    {
        $this->signedN8nJson('GET', '/api/v1/integrations/n8n/health')->assertStatus(401);
    }

    public function test_n8n_health_endpoint_returns_403_with_invalid_token(): void
    {
        $this->withToken('invalid-token')->signedN8nJson('GET', '/api/v1/integrations/n8n/health')->assertStatus(403);
    }

    public function test_n8n_health_endpoint_returns_200_with_valid_token(): void
    {
        $token = $this->n8nToken;

        $this->withToken($token)->signedN8nJson('GET', '/api/v1/integrations/n8n/health')->assertStatus(200);
    }


}
