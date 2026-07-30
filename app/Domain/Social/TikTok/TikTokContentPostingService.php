<?php

namespace App\Domain\Social\TikTok;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Domain\Social\TikTok\Strategies\TikTokMediaTransferStrategy;
use App\Exceptions\Social\TikTokApiException;

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
                throw new TikTokApiException("Impossibile recuperare info dal creator TikTok: " . $response->json('error.message', 'Errore sconosciuto'));
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

        return match ($publishMode) {
            'draft' => $this->initializeVideoDraftUpload($accessToken, $postData, $strategy),
            'direct' => $this->initializeVideoDirectPost($accessToken, $postData, $strategy),
            default => throw new TikTokApiException("TikTok publishing non configurato o non ancora abilitato."),
        };
    }

    private function initializeVideoDraftUpload(
        string $accessToken,
        array $postData,
        TikTokMediaTransferStrategy $strategy
    ): array {
        $payload = $strategy->applyStrategy(
            $accessToken,
            [],
            [$postData['video_url']],
            'video'
        );

        $response = Http::withToken($accessToken)
            ->withHeaders([
                'Content-Type' => 'application/json; charset=UTF-8',
            ])
            ->post("{$this->apiBase}/v2/post/publish/inbox/video/init/", $payload);

        if (!$response->successful()) {
            throw new TikTokApiException("TikTok API Draft Upload Video fallito: " . $response->body());
        }

        $data = $response->json();

        if (($data['error']['code'] ?? 'ok') !== 'ok') {
            throw new TikTokApiException("TikTok Draft Upload Video Error: " . ($data['error']['message'] ?? 'Unknown error'));
        }

        return [
            'publish_id' => $data['data']['publish_id'] ?? null,
            'mode' => 'draft',
            'response' => $data,
        ];
    }

    private function initializeVideoDirectPost(
        string $accessToken,
        array $postData,
        TikTokMediaTransferStrategy $strategy
    ): array {
        if (!config('services.tiktok.direct_publish_enabled', false)) {
            throw new TikTokApiException("TikTok direct publish disabilitato. Usa TIKTOK_DELIVERY_MODE=draft.");
        }

        $basePayload = [
            'post_info' => [
                'title' => mb_substr($postData['title'] ?? '', 0, 2200),
                'privacy_level' => $postData['privacy_level'] ?? 'SELF_ONLY',
                'disable_comment' => $postData['disable_comment'] ?? false,
                'disable_duet' => $postData['disable_duet'] ?? true,
                'disable_stitch' => $postData['disable_stitch'] ?? true,
            ],
        ];

        $payload = $strategy->applyStrategy(
            $accessToken,
            $basePayload,
            [$postData['video_url']],
            'video'
        );

        $response = Http::withToken($accessToken)
            ->withHeaders([
                'Content-Type' => 'application/json; charset=UTF-8',
            ])
            ->post("{$this->apiBase}/v2/post/publish/video/init/", $payload);

        if (!$response->successful()) {
            throw new TikTokApiException("TikTok API Direct Post Video fallito: " . $response->body());
        }

        $data = $response->json();

        if (($data['error']['code'] ?? 'ok') !== 'ok') {
            throw new TikTokApiException("TikTok Direct Post Video Error: " . ($data['error']['message'] ?? 'Unknown error'));
        }

        return [
            'publish_id' => $data['data']['publish_id'] ?? null,
            'mode' => 'direct',
            'response' => $data,
        ];
    }

    /**
     * Inizializza e pubblica un carosello di immagini (Photo Mode).
     */
    public function initializePhotoPost(string $accessToken, array $postData, TikTokMediaTransferStrategy $strategy): array
    {
        if (!config('services.tiktok.enable_photo_mode', false)) {
            throw new TikTokApiException("TikTok Photo Mode disabilitato in questa release.");
        }

        throw new TikTokApiException("TikTok Photo Mode non ancora implementato.");
    }

    /**
     * Controlla lo stato della pubblicazione asincrona
     */
    public function getPostStatus(string $accessToken, string $publishId): \App\Domain\Social\DTOs\TikTokPostStatusResult
    {
        $publishMode = config('services.tiktok.delivery_mode', 'disabled');

        if ($publishMode === 'disabled') {
            throw new TikTokApiException("TikTok publishing  disabilitato.");
        }

        $response = Http::withToken($accessToken)
            ->post("{$this->apiBase}/v2/post/publish/status/fetch/", [
                'publish_id' => $publishId
            ]);

        $responseData = $response->json();
        if (!is_array($responseData)) {
            $responseData = [];
        }
        $requestId = $response->header('X-Tt-Logid')
            ?: $response->header('X-Request-Id')
            ?: null;

        if (!$response->successful()) {
            $httpStatus = $response->status();
            $isAuthError = in_array($httpStatus, [401, 403], true);
            $isTemporaryError = in_array($httpStatus, [408, 425, 429], true)
                || $httpStatus >= 500;

            return new \App\Domain\Social\DTOs\TikTokPostStatusResult(
                status: 'HTTP_ERROR',
                responseData: $responseData,
                isPermanentError: !$isAuthError &&
                    !$isTemporaryError &&
                    $httpStatus >= 400 &&
                    $httpStatus < 500,
                errorMessage: 'TikTok Fetch Status fallito: ' .
                    ($responseData['error']['message'] ?? "HTTP {$httpStatus}"),
                httpStatus: $httpStatus,
                requestId: $requestId,
                isTemporaryError: $isTemporaryError,
                isAuthError: $isAuthError,
            );
        }

        $data = $responseData;
        
        if (isset($data['error']['code']) && $data['error']['code'] !== 'ok') {
            $errorCode = strtolower((string) $data['error']['code']);
            $isAuthError = str_contains($errorCode, 'access_token')
                || str_contains($errorCode, 'scope')
                || str_contains($errorCode, 'auth');
            $isTemporaryError = str_contains($errorCode, 'rate_limit')
                || str_contains($errorCode, 'internal')
                || str_contains($errorCode, 'timeout');

            return new \App\Domain\Social\DTOs\TikTokPostStatusResult(
                status: 'API_ERROR',
                responseData: $data,
                isPermanentError: !$isAuthError && !$isTemporaryError,
                errorMessage: 'TikTok Status Error: ' .
                    ($data['error']['message'] ?? 'Unknown error'),
                httpStatus: $response->status(),
                requestId: $requestId,
                isTemporaryError: $isTemporaryError,
                isAuthError: $isAuthError,
            );
        }

        $status = $data['data']['status'] ?? 'UNKNOWN';
        $failReason = $data['data']['fail_reason'] ?? null;

        return new \App\Domain\Social\DTOs\TikTokPostStatusResult(
            status: $status,
            responseData: $data,
            httpStatus: $response->status(),
            requestId: $requestId,
            failReason: is_string($failReason) && $failReason !== '' ? $failReason : null,
        );
    }
}

