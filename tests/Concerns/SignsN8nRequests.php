<?php

namespace Tests\Concerns;

use Illuminate\Testing\TestResponse;

trait SignsN8nRequests
{
    protected string $n8nToken;

    private string $n8nSigningSecret;

    private int $n8nRequestSequence = 0;

    protected function setUpSignsN8nRequests(): void
    {
        $this->n8nToken = bin2hex(random_bytes(32));
        $this->n8nSigningSecret = bin2hex(random_bytes(32));
        $this->n8nRequestSequence = 0;
        config([
            'services.n8n.token' => $this->n8nToken,
            'services.n8n.signing_secret' => $this->n8nSigningSecret,
            'services.n8n.require_signature' => true,
            'services.n8n.require_idempotency_key' => true,
        ]);
    }

    protected function signedN8nJson(string $method, string $uri, array $data = [], array $headers = [], int $options = 0): TestResponse
    {
        $target = parse_url($uri, PHP_URL_PATH) ?: '/';
        $query = parse_url($uri, PHP_URL_QUERY);
        if ($query !== null && $query !== false) {
            $target .= '?'.$query;
        }
        /** Ogni tentativo ha una firma distinta, anche quando ripete lo stesso payload. */
        $timestamp = (string) (now()->timestamp + $this->n8nRequestSequence++);
        $payload = implode("\n", [$timestamp, strtoupper($method), $target, json_encode($data, $options | JSON_THROW_ON_ERROR)]);
        $headers += [
            'X-N8N-Timestamp' => $timestamp,
            'X-N8N-Signature' => 'sha256='.hash_hmac('sha256', $payload, $this->n8nSigningSecret),
            'Idempotency-Key' => bin2hex(random_bytes(16)),
        ];

        return $this->json($method, $uri, $data, $headers, $options);
    }
}
