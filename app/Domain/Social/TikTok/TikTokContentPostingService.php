<?php

namespace App\Domain\Social\TikTok;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Domain\Social\TikTok\Strategies\TikTokMediaTransferStrategy;
use Exception;

class TikTokContentPostingService
{
    private string $apiBase;

    public function __construct()
    {
        $this->apiBase = config('services.tiktok.api_base', 'https://open.tiktokapis.com');
    }

    public function queryCreatorInfo(string $accessToken, ?string $accountId = null): array
    {
        // Cache creator info for 24h based on account id or token fallback
        $cacheKey = "tiktok_creator_info_" . ($accountId ?: md5($accessToken));

        return Cache::remember($cacheKey, 86400, function () use ($accessToken) {
            $response = Http::withToken($accessToken)
                ->post("{$this->apiBase}/v2/post/publish/creator_info/query/");

            if (!$response->successful()) {
                Log::error("TikTok queryCreatorInfo failed", [
                    'status' => $response->status(),
                    'error_code' => $response->json('error.code'),
                    'error_message' => $response->json('error.message'),
                ]);
                throw new Exception("Impossibile recuperare info dal creator TikTok: " . $response->json('error.message', 'Errore sconosciuto'));
            }

            return $response->json('data');
        });
    }

    /**
     * Inizializza e pubblica (o carica in draft) il video.
     */
    public function initializeVideoPost(string $accessToken, array $postData, TikTokMediaTransferStrategy $strategy): array
    {
        $publishMode = config('services.tiktok.delivery_mode', 'disabled');

        if ($publishMode === 'disabled') {
            throw new Exception("TikTok publishing non configurato o non ancora abilitato.");
        }

        $basePayload = [
            'post_info' => [
                'title' => $postData['title'] ?? '',
                'privacy_level' => 'SELF_ONLY',
                'disable_comment' => false,
                'disable_duet' => true,
                'disable_stitch' => true,
            ]
        ];

        $payload = $strategy->applyStrategy($accessToken, $basePayload, [$postData['video_url']], 'video');

        $response = Http::withToken($accessToken)
            ->post("{$this->apiBase}/v2/post/publish/video/init/", $payload);

        if (!$response->successful()) {
            throw new Exception("TikTok API Init Video Fallito: " . $response->body());
        }

        $data = $response->json();
        if (isset($data['error']['code']) && $data['error']['code'] !== 'ok') {
            throw new Exception("TikTok Init Video Error: " . ($data['error']['message'] ?? 'Unknown error'));
        }

        return [
            'publish_id' => $data['data']['publish_id'] ?? null,
            'response' => $data
        ];
    }

    /**
     * Inizializza e pubblica un carosello di immagini (Photo Mode).
     */
    public function initializePhotoPost(string $accessToken, array $postData, TikTokMediaTransferStrategy $strategy): array
    {
        $publishMode = config('services.tiktok.delivery_mode', 'disabled');

        if ($publishMode === 'disabled') {
            throw new Exception("TikTok publishing non configurato o non ancora abilitato.");
        }

        $basePayload = [
            'post_info' => [
                'title' => $postData['title'] ?? '',
                'privacy_level' => 'SELF_ONLY',
                'disable_comment' => false,
                'disable_duet' => true,
                'disable_stitch' => true,
            ]
        ];

        $payload = $strategy->applyStrategy($accessToken, $basePayload, $postData['photo_urls'], 'photo');

        $response = Http::withToken($accessToken)
            ->post("{$this->apiBase}/v2/post/publish/content/init/", $payload);

        if (!$response->successful()) {
            throw new Exception("TikTok API Init Photo Fallito: " . $response->body());
        }

        $data = $response->json();
        if (isset($data['error']['code']) && $data['error']['code'] !== 'ok') {
            throw new Exception("TikTok Init Photo Error: " . ($data['error']['message'] ?? 'Unknown error'));
        }

        return [
            'publish_id' => $data['data']['publish_id'] ?? null,
            'response' => $data
        ];
    }

    /**
     * Controlla lo stato della pubblicazione asincrona
     */
    public function getPostStatus(string $accessToken, string $publishId): string
    {
        $publishMode = config('services.tiktok.delivery_mode', 'disabled');

        if ($publishMode === 'disabled') {
            throw new Exception("TikTok publishing è disabilitato.");
        }

        $response = Http::withToken($accessToken)
            ->post("{$this->apiBase}/v2/post/publish/status/fetch/", [
                'publish_id' => $publishId
            ]);

        if (!$response->successful()) {
            throw new Exception("TikTok Fetch Status Fallito: " . $response->body());
        }

        $data = $response->json();
        
        if (isset($data['error']['code']) && $data['error']['code'] !== 'ok') {
            throw new Exception("TikTok Status Error: " . ($data['error']['message'] ?? 'Unknown error'));
        }

        $status = $data['data']['status'] ?? 'UNKNOWN';

        return $status;
    }
}

