<?php

namespace App\Services\Integrations\Nextcloud;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NextcloudService
{
    private string $baseUrl;
    private string $username;
    private string $password;
    private string $webdavPath;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.nextcloud.base_url', env('NEXTCLOUD_BASE_URL', '')), '/');
        $this->username = config('services.nextcloud.username', env('NEXTCLOUD_USERNAME', ''));
        $this->password = config('services.nextcloud.password', env('NEXTCLOUD_PASSWORD', ''));
        $this->webdavPath = config('services.nextcloud.webdav_path', env('NEXTCLOUD_WEBDAV_PATH', '/remote.php/dav/files'));
    }

    public function isConfigured(): bool
    {
        return !empty($this->baseUrl) && !empty($this->username) && !empty($this->password);
    }

    /**
     * Elenca i file e le cartelle in un determinato percorso.
     * Mostra cartelle e filtra i file limitandosi alle immagini o video in base al tipo.
     */
    public function listFiles(string $path = '/', string $mediaKind = 'photo'): ?array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        // Nextcloud (SabreDAV) richiede che le cartelle terminino sempre con '/' se si fa PROPFIND su una dir
        $url = rtrim($this->buildWebdavUrl($path), '/') . '/';

        logger()->debug('NEXTCLOUD URL', [
            'path' => $path,
            'url' => $url,
        ]);

        try {
            $response = Http::timeout(10)->retry(2, 300)->withBasicAuth($this->username, $this->password)
                ->withHeaders([
                    'Depth' => '1',
                    'Content-Type' => 'application/xml',
                ])
                ->send('PROPFIND', $url, [
                    'body' => '<?xml version="1.0" encoding="utf-8"?><d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns"><d:prop><d:resourcetype/><d:getcontenttype/><d:getcontentlength/><d:getlastmodified/><oc:fileid/></d:prop></d:propfind>'
                ]);

            if (!$response->successful()) {
                Log::error("Nextcloud PROPFIND fallito per URL: {$url}. Status: " . $response->status() . " Body: " . $response->body());
                return null;
            }

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($response->body(), 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($xml === false) {
                Log::warning("Nextcloud PROPFIND returned malformed XML per URL: {$url}");
                return null;
            }
            $results = [];

            // Il primo elemento è sempre la directory corrente
            $isFirst = true;

            $dav = $xml->children('DAV:');
            $responses = $dav->response;

            if ($responses) {
                foreach ($responses as $res) {
                    if ($isFirst) {
                        $isFirst = false;
                        continue; // salta la directory corrente
                    }

                    $responseDav = $res->children('DAV:');
                    $href = (string) $responseDav->href;
                    // decodifica %20 ecc.
                    $href = urldecode($href);
                    $hrefPath = \Illuminate\Support\Str::after($href, $this->webdavPath . '/' . $this->username);

                    $propstat = null;
                    foreach ($responseDav->propstat as $p) {
                        $status = (string) $p->children('DAV:')->status;
                        if (str_contains($status, '200 OK')) {
                            $propstat = $p;
                            break;
                        }
                    }

                    if (!$propstat)
                        continue;

                    $prop = $propstat->children('DAV:')->prop;
                    if (!$prop)
                        continue;

                    $resourceType = $prop->resourcetype->children('DAV:');
                    $isDir = isset($resourceType->collection);

                    $contentType = (string) $prop->children('DAV:')->getcontenttype;
                    $size = (int) $prop->children('DAV:')->getcontentlength;

                    $name = basename($href);

                    // Filtro: mostriamo le cartelle, ma per i file mostriamo solo quelli del tipo scelto
                    if (!$isDir) {
                        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        $allowedExts = match ($mediaKind) {
                            'video' => ['mp4', 'mov', 'webm', 'm4v'],
                            default => ['jpg', 'jpeg', 'png', 'webp'],
                        };

                        // Fallback affidabile sull'estensione per prevenire bug di Nextcloud sui MIME types
                        if (!in_array($ext, $allowedExts)) {
                            continue;
                        }
                    }

                    $isImage = false;
                    $isVideo = false;
                    if (!$isDir) {
                        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp']);
                        $isVideo = in_array($ext, ['mp4', 'mov', 'webm', 'm4v']);
                    }

                    $ocProp = $prop->children('http://owncloud.org/ns');
                    $fileId = isset($ocProp->fileid) ? (string) $ocProp->fileid : null;

                    $results[] = [
                        'name' => $name,
                        'path' => $hrefPath,
                        'is_dir' => $isDir,
                        'size' => $size,
                        'content_type' => $contentType,
                        'file_id' => $fileId,
                        'is_image' => $isImage,
                        'is_video' => $isVideo,
                    ];
                }
            }

            return $results;

        } catch (\Exception $e) {
            Log::error('Errore connessione Nextcloud: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Scarica un file da Nextcloud e restituisce il contenuto.
     */
    public function downloadFile(string $remotePath): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $url = $this->buildWebdavUrl($remotePath);

        try {
            $response = Http::timeout(10)->retry(2, 300)->withBasicAuth($this->username, $this->password)
                ->get($url);

            if ($response->successful()) {
                return $response->body();
            }

            Log::error('Errore download file Nextcloud: ' . $response->status() . ' - ' . $response->body());
            return null;

        } catch (\Exception $e) {
            Log::error('Errore download Nextcloud: ' . $e->getMessage());
            return null;
        }
    }

    public function mediaRoot(string $mediaKind): string
    {
        return match ($mediaKind) {
            'video' => config('services.nextcloud.videos_root', '/'),
            default => config('services.nextcloud.photos_root', '/'),
        };
    }

    public function createPublicShare(string $path): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $url = $this->baseUrl . '/ocs/v2.php/apps/files_sharing/api/v1/shares';
        $headers = [
            'OCS-APIRequest' => 'true',
            'Accept' => 'application/json',
        ];

        try {
            $checkResponse = Http::timeout(10)->retry(2, 300)->withBasicAuth($this->username, $this->password)
                ->withHeaders($headers)
                ->get($url, ['path' => $path]);

            if ($checkResponse->successful()) {
                $data = $checkResponse->json();
                $shares = $data['ocs']['data'] ?? [];

                // ocs.data potrebbe essere un singolo oggetto o un array di oggetti,
                // gestiamo il caso in cui ci sono shares. Nextcloud solitamente ritorna un array di shares.
                if (is_array($shares)) {
                    // A volte Nextcloud ritorna ocs.data vuoto stringa/array se non ci sono shares
                    foreach ($shares as $share) {
                        if (is_array($share) && ($share['share_type'] ?? null) == 3 && !empty($share['url'])) {
                            $expiration = $share['expiration'] ?? null;
                            if (!$expiration || \Carbon\Carbon::parse($expiration)->isFuture()) {
                                return $share['url'];
                            }
                        }
                    }
                }
            }

            $payload = [
                'path' => $path,
                'shareType' => 3, // public link
                'permissions' => 1, // read
            ];
            
            $expireDays = config('services.nextcloud.share_expire_days', 7);
            if ($expireDays > 0) {
                $payload['expireDate'] = now()->addDays($expireDays)->toDateString();
            }

            $response = Http::timeout(10)->retry(2, 300)->withBasicAuth($this->username, $this->password)
                ->withHeaders($headers)
                ->asForm()
                ->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                return $data['ocs']['data']['url'] ?? null;
            }

            Log::error('Errore creazione share Nextcloud: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('Errore Nextcloud Share API: ' . $e->getMessage());
            return null;
        }
    }

    private function buildWebdavUrl(string $path = '/'): string
    {
        $baseUrl = rtrim(config('services.nextcloud.base_url'), '/');

        $webdavPath = trim(
            config('services.nextcloud.webdav_path', '/remote.php/dav/files'),
            '/'
        );

        $username = trim(
            config('services.nextcloud.username'),
            '/'
        );

        $path = $this->normalizePath($path);

        $encodedPath = collect(explode('/', trim($path, '/')))
            ->filter()
            ->map(fn ($segment) => rawurlencode($segment))
            ->implode('/');

        return $baseUrl
            . '/'
            . $webdavPath
            . '/'
            . rawurlencode($username)
            . ($encodedPath ? '/' . $encodedPath : '');
    }

    public function normalizePath(?string $path): string
    {
        $path = $path ?: '/';
        $decoded = rawurldecode($path);
        
        // Optional: Reject multiple encoded or paths containing % after decode
        if ($decoded !== $path && str_contains($decoded, '%')) {
            abort(400, 'Invalid path encoding');
        }

        $path = $decoded;

        // converte backslash Windows/JSON in slash normali
        $path = str_replace('\\', '/', $path);

        $segments = explode('/', $path);
        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '..') {
                abort(400, 'Invalid path segment');
            }
        }

        $path = '/' . ltrim($path, '/');
        // rimuove slash multipli
        $path = preg_replace('#/+#', '/', $path);
        return $path === '' ? '/' : $path;
    }

    public function previewResponse(string $path, int $width = 900, int $height = 900)
    {
        $path = $this->normalizePath($path);

        // 1. Prova preview nativa Nextcloud
        $previewUrl = rtrim(config('services.nextcloud.base_url'), '/')
            . '/index.php/core/preview.png';

        try {
            $preview = Http::timeout(8)
                ->withBasicAuth($this->username, $this->password)
                ->get($previewUrl, [
                    'file' => $path,
                    'x' => $width,
                    'y' => $height,
                    'a' => 'true',
                ]);

            $contentType = $preview->header('Content-Type', '');

            if (
                $preview->successful()
                && str_starts_with($contentType, 'image/')
                && strlen($preview->body()) > 100
            ) {
                return response($preview->body(), 200)
                    ->header('Content-Type', $contentType)
                    ->header('Cache-Control', 'private, max-age=600');
            }
        } catch (\Throwable $e) {
            logger()->warning('Nextcloud preview endpoint failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }

        // 2. Fallback sicuro: scarica il file via WebDAV
        $download = Http::timeout(12)
            ->withBasicAuth($this->username, $this->password)
            ->get($this->buildWebdavUrl($path));

        abort_unless($download->successful(), 404);

        $contentType = $download->header('Content-Type', 'application/octet-stream');

        abort_unless(str_starts_with($contentType, 'image/'), 415);

        return response($download->body(), 200)
            ->header('Content-Type', $contentType)
            ->header('Cache-Control', 'private, max-age=600');
    }
}
