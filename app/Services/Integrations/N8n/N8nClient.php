<?php

namespace App\Services\Integrations\N8n;

use App\Models\IntegrationLog;
use Illuminate\Support\Facades\Http;
use Exception;

class N8nClient
{
    // Invia un payload a n8n per la rigenerazione di un post social
    public function requestSocialPostRegeneration(array $payload): array
    {
        $url = config('services.n8n.regenerate_social_post_webhook_url');

        if (! $url) {
            throw new Exception('N8N_REGENERATE_SOCIAL_POST_WEBHOOK_URL non configurato.');
        }

        $log = IntegrationLog::create([
            'provider' => 'n8n',
            'direction' => 'outbound',
            'endpoint' => $url,
            'event' => 'regenerate_social_post',
            'payload' => $this->sanitizePayload($payload),
            'status' => 'processing',
        ]);

        try {
            $response = Http::timeout(10)->post($url, $payload);
            
            $log->update([
                'response' => $response->json() ?? $response->body(),
                'status_code' => $response->status(),
                'status' => $response->successful() ? 'processed' : 'failed',
                'processed_at' => now(),
            ]);

            if (! $response->successful()) {
                throw new Exception('N8n ha risposto con errore: ' . $response->status());
            }

            return $response->json() ?? [];
            
        } catch (Exception $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'processed_at' => now(),
            ]);

            throw $e;
        }
    }

    public function requestSingleSocialPostGeneration(array $payload): array
    {
        return $this->sendRequest(config('services.n8n.generate_social_post_webhook_url'), 'generate_social_post', $payload);
    }

    public function requestEditorialPlanGeneration(array $payload): array
    {
        return $this->sendRequest(config('services.n8n.generate_editorial_plan_webhook_url'), 'generate_editorial_plan', $payload);
    }

    public function sendWhatsappReviewLink(array $payload): array
    {
        return $this->sendRequest(config('services.n8n.send_whatsapp_review_webhook_url'), 'send_whatsapp_review', $payload);
    }

    public function requestSocialPostPublication(array $payload): array
    {
        return $this->sendRequest(config('services.n8n.publish_social_post_webhook_url'), 'publish_social_post', $payload);
    }

    private function sendRequest(?string $url, string $event, array $payload): array
    {
        if (! $url) {
            throw new Exception("Webhook URL per l'evento {$event} non configurato.");
        }

        $log = IntegrationLog::create([
            'provider' => 'n8n',
            'direction' => 'outbound',
            'endpoint' => $url,
            'event' => $event,
            'payload' => $this->sanitizePayload($payload),
            'status' => 'processing',
        ]);

        try {
            $response = Http::timeout(10)->post($url, $payload);
            
            $log->update([
                'response' => $response->json() ?? $response->body(),
                'status_code' => $response->status(),
                'status' => $response->successful() ? 'processed' : 'failed',
                'processed_at' => now(),
            ]);

            if (! $response->successful()) {
                throw new Exception("N8n ha risposto con errore: " . $response->status());
            }

            return $response->json() ?? [];
            
        } catch (Exception $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'processed_at' => now(),
            ]);

            throw $e;
        }
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
