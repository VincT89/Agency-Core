<?php

namespace App\Domain\Social\Services;

use App\Support\Http\ProviderErrorSanitizer;
use App\Support\Http\SocialProviderHttp;
use Illuminate\Support\Facades\Log;

class InstagramContainerStatusService
{
    /**
     * Controlla lo stato del container su Meta ed esegue il publish se pronto.
     * Ritorna un DTO puro, senza fare side-effects sul DB.
     */
    public function getContainerStatus(string $containerId, string $accessToken, ?string $correlationId = null): InstagramContainerStatusResult
    {
        try {
            $client = SocialProviderHttp::meta(retrySafe: true)->withHeaders([
                'X-Correlation-Id' => $correlationId ?? 'none',
            ]);

            $graphVersion = config('services.meta.graph_version', 'v19.0');
            $statusEndpoint = "https://graph.facebook.com/{$graphVersion}/{$containerId}";
            $statusResponse = $client->get($statusEndpoint, [
                'fields' => 'status_code,status',
                'access_token' => $accessToken,
            ]);

            if (! $statusResponse->successful()) {
                $errorData = $statusResponse->json();

                if ($statusResponse->clientError()) {
                    $errorCode = $errorData['error']['code'] ?? null;
                    // Codici temporanei: 4, 17, 32, 613 (rate limits/throttling)
                    $temporaryCodes = [4, 17, 32, 613];

                    if (! in_array($errorCode, $temporaryCodes)) {
                        return new InstagramContainerStatusResult(
                            status: 'ERROR',
                            isPermanentError: true,
                            errorMessage: 'Errore permanente (4xx) da Instagram nel recupero status Container: '.($errorData['error']['message'] ?? 'Sconosciuto'),
                            responseData: $errorData
                        );
                    }
                }

                // Errore generico (o 5xx o rate limit). Ritorniamo error non permanente per far scattare retry.
                return new InstagramContainerStatusResult(
                    status: 'UNKNOWN',
                    isPermanentError: false,
                    errorMessage: ProviderErrorSanitizer::message(
                        $statusResponse,
                        'Errore nel recupero status Container'
                    ),
                    responseData: ProviderErrorSanitizer::payload($statusResponse)
                );
            }

            $statusData = $statusResponse->json();
            $statusCode = $statusData['status_code'] ?? 'UNKNOWN';

            $isPermanent = in_array($statusCode, ['ERROR', 'EXPIRED']);

            return new InstagramContainerStatusResult(
                status: $statusCode,
                isPermanentError: $isPermanent,
                errorMessage: $isPermanent ? 'Instagram ha riportato un errore nel container o è scaduto.' : null,
                responseData: $statusData
            );

        } catch (\Exception $e) {
            Log::error('InstagramContainerStatusService Exception', [
                'error' => $e->getMessage(),
                'container_id' => $containerId,
            ]);

            return new InstagramContainerStatusResult(
                status: 'UNKNOWN',
                isPermanentError: false,
                errorMessage: 'Eccezione interna durante check: '.$e->getMessage(),
                responseData: null
            );
        }
    }

    public function createCarouselParent(string $igAccountId, array $childrenIds, string $caption, string $accessToken, ?string $correlationId = null): array
    {
        $client = SocialProviderHttp::meta()->withHeaders([
            'X-Correlation-Id' => $correlationId ?? 'none',
        ]);

        $graphVersion = config('services.meta.graph_version', 'v19.0');
        $baseEndpoint = "https://graph.facebook.com/{$graphVersion}/{$igAccountId}";

        $carouselPayload = [
            'access_token' => $accessToken,
            'caption' => $caption,
            'media_type' => 'CAROUSEL',
            'children' => implode(',', $childrenIds),
        ];

        $containerResponse = $client->post("{$baseEndpoint}/media", $carouselPayload);

        if (! $containerResponse->successful()) {
            throw new \Exception(
                ProviderErrorSanitizer::message(
                    $containerResponse,
                    'Errore IG Carousel Container Parent'
                )
            );
        }

        return $containerResponse->json();
    }

    public function publishContainer(string $igAccountId, string $containerId, string $accessToken, ?string $correlationId = null): array
    {
        $client = SocialProviderHttp::meta()->withHeaders([
            'X-Correlation-Id' => $correlationId ?? 'none',
        ]);

        $graphVersion = config('services.meta.graph_version', 'v19.0');
        $publishEndpoint = "https://graph.facebook.com/{$graphVersion}/{$igAccountId}/media_publish";
        $publishResponse = $client->post($publishEndpoint, [
            'creation_id' => $containerId,
            'access_token' => $accessToken,
        ]);

        if (! $publishResponse->successful()) {
            throw new \Exception(
                ProviderErrorSanitizer::message(
                    $publishResponse,
                    'Errore nella media_publish di Instagram'
                )
            );
        }

        return $publishResponse->json();
    }

    public function getMediaPermalink(
        string $mediaId,
        string $accessToken,
        ?string $correlationId = null
    ): ?string {
        try {
            $client = SocialProviderHttp::meta(retrySafe: true)->withHeaders([
                'X-Correlation-Id' => $correlationId ?? 'none',
            ]);
            $graphVersion = config('services.meta.graph_version', 'v19.0');
            $response = $client->get(
                "https://graph.facebook.com/{$graphVersion}/{$mediaId}",
                [
                    'fields' => 'permalink',
                    'access_token' => $accessToken,
                ]
            );

            if (! $response->successful()) {
                Log::warning('Instagram permalink fetch failed', [
                    'media_id' => $mediaId,
                    ...ProviderErrorSanitizer::context($response),
                ]);

                return null;
            }

            $permalink = $response->json('permalink');
            if (! is_string($permalink) || ! filter_var($permalink, FILTER_VALIDATE_URL)) {
                return null;
            }

            $scheme = strtolower((string) parse_url($permalink, PHP_URL_SCHEME));
            $host = strtolower((string) parse_url($permalink, PHP_URL_HOST));

            if (
                $scheme !== 'https'
                || ($host !== 'instagram.com' && ! str_ends_with($host, '.instagram.com'))
            ) {
                return null;
            }

            return $permalink;
        } catch (\Throwable $exception) {
            Log::warning('Instagram permalink fetch exception', [
                'media_id' => $mediaId,
                'exception' => $exception::class,
            ]);

            return null;
        }
    }
}
