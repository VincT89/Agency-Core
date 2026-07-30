<?php

namespace App\Services\Integrations\N8n;

use App\Models\IntegrationLog;
use App\Support\Http\ProviderErrorSanitizer;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class N8nClient
{
    public function requestMarketingCampaignPostRegeneration(array $payload): array
    {
        return $this->sendRequest(
            config('services.n8n.regenerate_social_post_webhook_url'),
            'marketing_campaign_post_regeneration',
            $payload
        );
    }

    public function submitMarketingCampaignPost(array $payload): array
    {
        // Usa una url dedicata se presente, o ripiega su quella generale dei post per ora
        $url = config('services.n8n.submit_marketing_campaign_post_webhook_url')
            ?? config('services.n8n.generate_social_post_webhook_url');

        return $this->sendRequest($url, 'submit_marketing_campaign_post', $payload);
    }

    public function sendChatbotOutgoingMessage(array $payload): array
    {
        $payload['event'] = 'chatbot_outgoing_message';

        return $this->sendRequest(
            config('services.n8n.chatbot_outgoing_message_webhook_url'),
            'chatbot_outgoing_message',
            $payload
        );
    }

    private function sendRequest(?string $url, string $event, array $payload): array
    {
        if (! $url) {
            throw new RuntimeException(
                "Webhook URL per l'evento {$event} non configurato."
            );
        }

        $log = IntegrationLog::create([
            'provider' => 'n8n',
            'direction' => 'outbound',
            'endpoint' => $this->safeEndpoint($url),
            'event' => $event,
            'payload' => $this->sanitizePayload($payload),
            'status' => 'processing',
        ]);

        try {
            $request = Http::connectTimeout(
                (int) config('services.n8n.connect_timeout', 5)
            )->timeout(
                (int) config('services.n8n.timeout', 15)
            );
            if ($token = config('services.n8n.token')) {
                $request = $request->withToken($token);
            }
            $response = $request->post($url, $payload);

            $log->update([
                'response' => $this->safeResponse($response),
                'status_code' => $response->status(),
                'status' => $response->successful() ? 'processed' : 'failed',
                'processed_at' => now(),
            ]);

            if (! $response->successful()) {
                throw new RuntimeException(
                    ProviderErrorSanitizer::message(
                        $response,
                        'N8n ha risposto con errore'
                    )
                );
            }

            $responsePayload = $response->json();

            return is_array($responsePayload) ? $responsePayload : [];

        } catch (Throwable $e) {
            $safeError = ProviderErrorSanitizer::safeText($e->getMessage());
            $log->update([
                'status' => 'failed',
                'error_message' => $safeError,
                'processed_at' => now(),
            ]);

            throw new RuntimeException(
                $safeError,
                (int) $e->getCode(),
                $e
            );
        }
    }

    private function safeEndpoint(string $url): string
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT);

        if (! is_string($scheme) || ! is_string($host)) {
            return '[invalid webhook URL]';
        }

        return strtolower($scheme).'://'.strtolower($host)
            .(is_int($port) ? ':'.$port : '');
    }

    private function safeResponse(Response $response): array
    {
        return [
            'status' => $response->status(),
            'payload' => ProviderErrorSanitizer::payload($response),
        ];
    }

    private function sanitizePayload(array $payload): array
    {
        $sensitive = [
            'access_token',
            'refresh_token',
            'api_key',
            'client_secret',
            'authorization',
            'password',
        ];

        foreach ($payload as $key => $value) {
            if (in_array(strtolower($key), $sensitive, true)) {
                $payload[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $payload[$key] = $this->sanitizePayload($value);
            }
        }

        return $payload;
    }
}
