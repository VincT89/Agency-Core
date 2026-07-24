<?php

namespace App\Domain\Social\Services;

use App\Models\MarketingCampaignPostMedia;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SocialMediaPublicUrlService
{
    /**
     * Risolve un MarketingCampaignPostMedia in un DTO/array contenente
     * l'URL pubblico validato e lo snapshot diagnostico.
     */
    public function getValidatedPublicUrl(MarketingCampaignPostMedia $media, ?string $correlationId = null): array
    {
        $url = $this->generateUrl($media, $correlationId);
        
        $this->ensureSecureHost($url);
        
        $this->enforceExtensionWhitelist($media);
        
        $diagnostic = $this->performPreflightValidation($url, $media, $correlationId);
        
        return [
            'url' => $url,
            'diagnostic' => $diagnostic,
        ];
    }

    /**
     * Risolve un array o Collection di MarketingCampaignPostMedia in array di DTO.
     */
    public function getValidatedPublicUrls(iterable $mediaCollection, ?string $correlationId = null): array
    {
        $results = [];
        foreach ($mediaCollection as $media) {
            $results[] = $this->getValidatedPublicUrl($media, $correlationId);
        }
        return $results;
    }

    /**
     * Risolve l'URL (genera signed URL, fallback locale o restituisce URL pubblico esistente).
     */
    private function generateUrl(MarketingCampaignPostMedia $media, ?string $correlationId = null): string
    {
        $disk = $media->disk ?? 'local';
        $path = $media->path;
        $source = $media->source ?? 'local';

        // 1. Nextcloud Share URL
        if ($source === 'nextcloud' && !empty($media->nextcloud_share_url)) {
            $url = $media->nextcloud_share_url;
            if (!str_ends_with($url, '/download')) {
                $url = rtrim($url, '/') . '/download';
            }
            return $url;
        }

        // 2. Link dedicato esistente (Local/Public disk route)
        if (($source === 'local' || $disk === 'public') && !empty($path)) {
            $ttlMinutes = config('services.tiktok.media_url_ttl', 1440); // 24 ore
            return \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'social.media.delivery',
                now()->addMinutes($ttlMinutes),
                ['media' => $media->id]
            );
        }

        // 3. Cloud Storage (S3) Temporary URL
        if ($disk === 's3' && !empty($path)) {
            $ttlMinutes = config('services.tiktok.media_url_ttl', 1440); // 24 ore
            return Storage::disk($disk)->temporaryUrl($path, now()->addMinutes($ttlMinutes));
        }

        // 4. Fallback esistente per URL già esplicito non coperto dai precedenti
        if (empty($path) && !empty($media->url)) {
            return $media->url;
        }

        throw new Exception("Impossibile generare un URL pubblico per questo media (source: $source, disk: $disk). È richiesto un provider compatibile.");
    }

    /**
     * Controlla lo scheme e l'host
     */
    private function ensureSecureHost(string $url): void
    {
        if (config('social.url_validation') === false) {
            return;
        }

        $parsed = parse_url($url);
        
        if (($parsed['scheme'] ?? '') !== 'https') {
            throw new Exception("L'URL generato non utilizza HTTPS. Il provider rifiuterà la connessione. URL Scheme: " . ($parsed['scheme'] ?? 'none'));
        }

        $host = $parsed['host'] ?? '';
        
        $isLocalIPv4 = in_array($host, ['localhost', '127.0.0.1']) 
            || str_starts_with($host, '192.168.') 
            || str_starts_with($host, '10.')
            || str_starts_with($host, '169.254.')
            || preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $host);
        $isLocalIPv6 = in_array($host, ['::1']) || str_starts_with(strtolower($host), 'fc') || str_starts_with(strtolower($host), 'fd') || str_starts_with(strtolower($host), 'fe80');
        
        if ($isLocalIPv4 || $isLocalIPv6) {
            throw new Exception("L'URL generato punta a un host privato locale ($host).");
        }
    }

    /**
     * Esegue la HEAD request con fallback GET Range: bytes=0-0
     */
    private function performPreflightValidation(string $url, MarketingCampaignPostMedia $media, ?string $correlationId = null): array
    {
        $correlationId ??= Str::uuid()->toString();

        if (config('social.url_validation') === false) {
            return [
                'host' => 'localhost',
                'path_hash' => md5(parse_url($url, PHP_URL_PATH) ?? ''),
                'content_type' => 'image/png',
                'content_length' => 1024,
                'status' => 200,
                'redirect_count' => 0,
                'latency_ms' => 0,
                'validation_method' => 'BYPASSED',
                'expires_at' => now()->addMinutes(1440)->toIso8601String(),
                'correlation_id' => $correlationId,
            ];
        }
        
        try {
            $startTime = microtime(true);
            
            $response = Http::withHeaders(['X-Correlation-ID' => $correlationId])
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 1,
                        'strict' => true,
                        'track_redirects' => true
                    ],
                'connect_timeout' => 5,
                'timeout' => 15,
            ])->head($url);

            $method = 'HEAD';
            
            if ($response->status() === 405 || $response->status() === 403) {
                $response = Http::withHeaders(['Range' => 'bytes=0-0'])
                    ->withOptions([
                        'allow_redirects' => [
                            'max' => 1,
                            'strict' => true,
                            'track_redirects' => true
                        ],
                        'connect_timeout' => 5,
                        'timeout' => 15,
                        'stream' => true, // Essenziale: evita la saturazione della RAM se il server ignora il Range e risponde con 200 e tutto il file
                    ])->get($url);
                $method = 'GET_RANGE';

                // Chiudiamo subito lo stream, ci interessano solo gli header e lo status
                if ($response->toPsrResponse() && $response->toPsrResponse()->getBody()) {
                    $response->toPsrResponse()->getBody()->close();
                }
            }
            
            $latencyMs = round((microtime(true) - $startTime) * 1000);
            
            if ($response->transferStats && $response->transferStats->getHandlerStats()) {
                $stats = $response->transferStats->getHandlerStats();
                $redirectCount = $stats['redirect_count'] ?? 0;
                $finalUrl = $stats['url'] ?? $url;
            } else {
                $redirectCount = 0;
                $finalUrl = $url;
            }

            if ($redirectCount > 0) {
                $this->ensureSecureHost($finalUrl);
            }

            if (!$response->successful() && $response->status() !== 206) {
                throw new Exception("Pre-flight fallito con status " . $response->status() . " ($method)");
            }

            $contentType = $response->header('Content-Type');
            $this->validateMimeSemantic($contentType, $media);

            $parsedOriginal = parse_url($url);

            // expires_at dinamico: calcolato solo per path firmati o s3
            $expiresAt = null;
            if ($parsedOriginal['host'] === parse_url(config('app.url'), PHP_URL_HOST) && str_contains($url, 'signature=')) {
                $expiresAt = now()->addMinutes(config('services.tiktok.media_url_ttl', 1440))->toIso8601String();
            } elseif (str_contains($url, 'X-Amz-Signature=')) {
                $expiresAt = now()->addMinutes(config('services.tiktok.media_url_ttl', 1440))->toIso8601String();
            }

            $diagnostic = [
                'host' => $parsedOriginal['host'] ?? '',
                'path_hash' => md5($parsedOriginal['path'] ?? ''),
                'content_type' => $contentType,
                'content_length' => $response->header('Content-Length') ?? $response->header('Content-Range'),
                'status' => $response->status(),
                'redirect_count' => $redirectCount,
                'latency_ms' => $latencyMs,
                'validation_method' => $method,
                'expires_at' => $expiresAt,
                'correlation_id' => $correlationId,
            ];

            Log::info("MediaDeliveryGateway validation success", $diagnostic);
            return $diagnostic;

        } catch (\Exception $e) {
            $errorDiagnostic = [
                'correlation_id' => $correlationId,
                'error' => $e->getMessage(),
                'latency_ms' => round((microtime(true) - $startTime) * 1000)
            ];
            Log::error("MediaDeliveryGateway Validation Error", $errorDiagnostic);
            throw new Exception("MediaDeliveryGateway Validation Error: " . $e->getMessage());
        }
    }

    private function validateMimeSemantic(?string $mime, MarketingCampaignPostMedia $media): void
    {
        if (!$mime) {
            return;
        }
        
        $mimeLower = strtolower(trim(explode(';', $mime)[0]));
        
        $allowedPrefixes = ['image/', 'video/'];
        $allowedExact = ['application/octet-stream', 'binary/octet-stream'];
        
        $isAllowed = false;
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($mimeLower, $prefix)) {
                $isAllowed = true;
                break;
            }
        }
        
        // Se è octet-stream, accettiamolo solo se il nostro media è dichiaratamente un video o immagine
        if (!$isAllowed && in_array($mimeLower, $allowedExact)) {
            $isAllowed = $media->isVideo() || ($media->media_type === 'image');
        }
        
        if (!$isAllowed) {
            throw new Exception("Il content-type restituito ($mime) non è supportato per i media di questo publisher o non corrisponde all'estensione/media-type atteso.");
        }
    }

    private function enforceExtensionWhitelist(MarketingCampaignPostMedia $media): void
    {
        $sourcePath = $media->path ?: $media->url ?: $media->nextcloud_share_url ?: '';
        $parsedPath = parse_url($sourcePath, PHP_URL_PATH) ?? $sourcePath;
        $extension = strtolower(pathinfo($parsedPath, PATHINFO_EXTENSION));

        // Se Nextcloud Share o URL esterni nascondono l'estensione, usiamo il fallback sul mime_type noto
        if (empty($extension) && $media->mime_type) {
            $mimeMap = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'video/mp4' => 'mp4',
                'video/quicktime' => 'mov',
                'video/webm' => 'webm',
            ];
            $extension = $mimeMap[strtolower($media->mime_type)] ?? '';
        }

        $whitelist = [
            'jpg', 'jpeg', 'png', 'gif', 'webp', // images
            'mp4', 'mov', 'webm'                 // videos
        ];

        if (!in_array($extension, $whitelist)) {
            throw new Exception("Media abortito (Hardening): estensione file '.{$extension}' non autorizzata per la pubblicazione social.");
        }
    }
}
