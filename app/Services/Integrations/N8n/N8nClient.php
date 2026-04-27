<?php

namespace App\Services\Integrations\N8n;

use App\Models\IntegrationLog;
use Illuminate\Support\Facades\Http;
use Exception;

class N8nClient
{
    /**
     * Invia un payload a n8n per la rigenerazione di un post social.
     *
     * @param array $payload
     * @return array
     * @throws Exception
     */
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
            'payload' => $payload,
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
}
