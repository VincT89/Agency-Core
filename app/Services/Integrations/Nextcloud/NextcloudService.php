<?php

namespace App\Services\Integrations\Nextcloud;

use App\Domain\Social\DTOs\NextcloudFileInfo;
use App\Exceptions\NextcloudShareException;
use App\Exceptions\Social\NextcloudFileNotFoundException;
use App\Exceptions\Social\NextcloudPermanentFailureException;
use App\Exceptions\Social\NextcloudTemporaryUnavailableException;
use App\Services\Integrations\Nextcloud\DTO\NextcloudPublicShareResult;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class NextcloudService
{
    private string $baseUrl;

    private string $username;

    private string $password;

    private string $webdavPath;

    private int $connectTimeout;

    private int $requestTimeout;

    private int $streamTimeout;

    private int $streamReadTimeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.nextcloud.base_url', ''), '/');
        $this->username = (string) config('services.nextcloud.username', '');
        $this->password = (string) config('services.nextcloud.password', '');
        $this->webdavPath = (string) config(
            'services.nextcloud.webdav_path',
            '/remote.php/dav/files'
        );
        $this->connectTimeout = max(
            1,
            (int) config('services.nextcloud.connect_timeout', 5)
        );
        $this->requestTimeout = max(
            $this->connectTimeout,
            (int) config('services.nextcloud.request_timeout', 15)
        );
        $this->streamTimeout = max(
            $this->requestTimeout,
            (int) config('services.nextcloud.stream_timeout', 300)
        );
        $this->streamReadTimeout = max(
            1,
            (int) config('services.nextcloud.stream_read_timeout', 30)
        );
    }

    public function isConfigured(): bool
    {
        $scheme = strtolower((string) parse_url($this->baseUrl, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true)
            && (! app()->environment('production') || $scheme === 'https')
            && $this->username !== ''
            && $this->password !== '';
    }

    /**
     * Elenca i file e le cartelle in un determinato percorso.
     * Mostra cartelle e filtra i file limitandosi alle immagini o video in base al tipo.
     */
    public function listFiles(string $path = '/', string $mediaKind = 'photo'): ?array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        // Nextcloud (SabreDAV) richiede che le cartelle terminino sempre con '/' se si fa PROPFIND su una dir
        $url = rtrim($this->buildWebdavUrl($path), '/').'/';

        try {
            $response = $this->idempotentRequest()
                ->withHeaders([
                    'Depth' => '1',
                    'Content-Type' => 'application/xml',
                ])
                ->send('PROPFIND', $url, [
                    'body' => '<?xml version="1.0" encoding="utf-8"?><d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns"><d:prop><d:resourcetype/><d:getcontenttype/><d:getcontentlength/><d:getlastmodified/><oc:fileid/></d:prop></d:propfind>',
                ]);

            if (! $response->successful()) {
                Log::error('Nextcloud PROPFIND fallito', [
                    ...$this->pathContext($path),
                    'status' => $response->status(),
                ]);

                return null;
            }

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($response->body(), 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($xml === false) {
                Log::warning('Nextcloud PROPFIND ha restituito XML non valido', [
                    ...$this->pathContext($path),
                    'status' => $response->status(),
                ]);

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
                    $hrefPath = $this->ownedPathFromHref($href);

                    $propstat = null;
                    foreach ($responseDav->propstat as $p) {
                        $status = (string) $p->children('DAV:')->status;
                        if (str_contains($status, '200 OK')) {
                            $propstat = $p;
                            break;
                        }
                    }

                    if (! $propstat) {
                        continue;
                    }

                    $prop = $propstat->children('DAV:')->prop;
                    if (! $prop) {
                        continue;
                    }

                    $resourceType = $prop->resourcetype->children('DAV:');
                    $isDir = isset($resourceType->collection);

                    $contentType = (string) $prop->children('DAV:')->getcontenttype;
                    $size = (int) $prop->children('DAV:')->getcontentlength;

                    $name = basename($href);

                    // Filtro: mostriamo le cartelle, ma per i file mostriamo solo quelli del tipo scelto
                    if (! $isDir) {
                        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        $allowedExts = match ($mediaKind) {
                            'video' => ['mp4', 'mov', 'webm', 'm4v'],
                            default => ['jpg', 'jpeg', 'png', 'webp'],
                        };

                        // Fallback affidabile sull'estensione per prevenire bug di Nextcloud sui MIME types
                        if (! in_array($ext, $allowedExts)) {
                            continue;
                        }
                    }

                    $isImage = false;
                    $isVideo = false;
                    if (! $isDir) {
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

        } catch (\Throwable $e) {
            Log::error('Errore connessione Nextcloud', [
                ...$this->pathContext($path),
                'exception' => $e::class,
            ]);

            return null;
        }
    }

    /**
     * Recupera le info dettagliate di un singolo file (depth 0).
     */
    public function getFileInfo(string $path): NextcloudFileInfo
    {
        if (! $this->isConfigured()) {
            throw new NextcloudPermanentFailureException('Nextcloud non è configurato.');
        }

        $path = $this->normalizePath($path);
        $url = rtrim($this->buildWebdavUrl($path), '/');

        try {
            $response = $this->idempotentRequest()
                ->withHeaders([
                    'Depth' => '0',
                    'Content-Type' => 'application/xml',
                ])
                ->send('PROPFIND', $url, [
                    'body' => '<?xml version="1.0" encoding="utf-8"?><d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns"><d:prop><d:resourcetype/><d:getcontenttype/><d:getcontentlength/><d:getlastmodified/><d:getetag/><oc:fileid/></d:prop></d:propfind>',
                ]);
        } catch (ConnectionException $e) {
            throw new NextcloudTemporaryUnavailableException(
                'Connessione a Nextcloud non disponibile.',
                0,
                $e
            );
        } catch (\Throwable $e) {
            throw new NextcloudTemporaryUnavailableException(
                'Richiesta a Nextcloud non riuscita.',
                0,
                $e
            );
        }

        if ($response->status() === 404) {
            throw new NextcloudFileNotFoundException("File not found on Nextcloud: {$path}");
        }

        if (! $response->successful()) {
            if (in_array($response->status(), [408, 425, 429], true) || $response->serverError()) {
                throw new NextcloudTemporaryUnavailableException(
                    "Nextcloud API returned {$response->status()}"
                );
            }

            throw new NextcloudPermanentFailureException(
                "Nextcloud API returned {$response->status()}"
            );
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response->body(), 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($xml === false) {
            throw new NextcloudTemporaryUnavailableException("Nextcloud XML parsing failed for {$path}");
        }

        $dav = $xml->children('DAV:');
        $res = $dav->response[0] ?? null;

        if (! $res) {
            throw new NextcloudTemporaryUnavailableException("Nextcloud invalid DAV response for {$path}");
        }

        $responseDav = $res->children('DAV:');
        $href = (string) $responseDav->href;
        $hrefPath = $this->ownedPathFromHref($href);

        if ($hrefPath !== $path) {
            throw new NextcloudPermanentFailureException(
                "Nextcloud returned metadata for an unexpected path. Requested: {$path}; returned: {$hrefPath}"
            );
        }

        $propstat = null;
        foreach ($responseDav->propstat as $p) {
            $status = (string) $p->children('DAV:')->status;
            if (str_contains($status, '200 OK')) {
                $propstat = $p;
                break;
            }
        }

        if (! $propstat) {
            throw new NextcloudTemporaryUnavailableException("Nextcloud missing 200 OK propstat for {$path}");
        }

        $prop = $propstat->children('DAV:')->prop;
        if (! $prop) {
            throw new NextcloudTemporaryUnavailableException("Nextcloud missing prop in response for {$path}");
        }

        $resourceType = $prop->resourcetype->children('DAV:');
        $isDir = isset($resourceType->collection);

        if ($isDir) {
            throw new NextcloudFileNotFoundException("Requested Nextcloud path is a directory: {$path}");
        }

        $contentType = (string) $prop->children('DAV:')->getcontenttype;
        $size = (int) $prop->children('DAV:')->getcontentlength;
        $etag = (string) $prop->children('DAV:')->getetag;
        $etag = trim($etag, '"');

        $ocProp = $prop->children('http://owncloud.org/ns');
        $fileId = isset($ocProp->fileid) ? (string) $ocProp->fileid : '';

        try {
            return new NextcloudFileInfo(
                path: $hrefPath,
                fileId: $fileId,
                etag: $etag,
                mimeType: $contentType,
                sizeBytes: $size
            );
        } catch (\InvalidArgumentException $e) {
            throw new NextcloudPermanentFailureException(
                "Nextcloud returned invalid metadata for {$path}: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Scarica un file da Nextcloud e restituisce il contenuto.
     */
    public function downloadFile(string $remotePath): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $url = $this->buildWebdavUrl($remotePath);

        try {
            $response = $this->idempotentRequest()->get($url);

            if ($response->successful()) {
                return $response->body();
            }

            Log::error('Errore download file Nextcloud', [
                ...$this->pathContext($remotePath),
                'status' => $response->status(),
            ]);

            return null;

        } catch (\Throwable $e) {
            Log::error('Errore download Nextcloud', [
                ...$this->pathContext($remotePath),
                'exception' => $e::class,
            ]);

            return null;
        }
    }

    /**
     * Scarica un file in streaming da Nextcloud (utile per video con Range).
     */
    public function streamFileResponse(
        string $remotePath,
        Request $request,
        ?string $expectedEtag = null
    ) {
        if (! $this->isConfigured()) {
            abort(404);
        }

        $url = $this->buildWebdavUrl($remotePath);

        $headers = [];
        if ($request->hasHeader('Range')) {
            $headers['Range'] = $request->header('Range');
        }
        if (filled($expectedEtag)) {
            $headers['If-Match'] = '"'.trim($expectedEtag, '"').'"';
        }

        $client = new Client;

        $options = [
            'auth' => [$this->username, $this->password],
            'stream' => true,
            'connect_timeout' => $this->connectTimeout,
            'timeout' => $this->streamTimeout,
            'read_timeout' => $this->streamReadTimeout,
            'allow_redirects' => false,
            'headers' => $headers,
            'http_errors' => false,
        ];

        try {
            $response = $client->request('GET', $url, $options);
            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200 && $statusCode !== 206) {
                abort(match ($statusCode) {
                    404 => 404,
                    412 => 409,
                    default => 500,
                });
            }

            $responseHeaders = [
                'Content-Type' => $response->getHeaderLine('Content-Type') ?: 'application/octet-stream',
                'Content-Length' => $response->getHeaderLine('Content-Length'),
                'Accept-Ranges' => $response->getHeaderLine('Accept-Ranges') ?: 'bytes',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
            ];

            if ($statusCode === 206) {
                $responseHeaders['Content-Range'] = $response->getHeaderLine('Content-Range');
            }

            // Aggiusto mime type per QuickTime e simili se non fornito da Nextcloud (spesso è octet-stream)
            $mime = $responseHeaders['Content-Type'];
            if ($mime === 'application/octet-stream' || empty($mime)) {
                if (str_ends_with(strtolower($remotePath), 'mp4')) {
                    $mime = 'video/mp4';
                }
                if (str_ends_with(strtolower($remotePath), 'webm')) {
                    $mime = 'video/webm';
                }
                if (str_ends_with(strtolower($remotePath), 'mov')) {
                    $mime = 'video/quicktime';
                }
                $responseHeaders['Content-Type'] = $mime;
            }

            $body = $response->getBody();

            return response()->stream(function () use ($body) {
                while (! $body->eof()) {
                    echo $body->read(8192);
                    flush();
                }
                $body->close();
            }, $statusCode, $responseHeaders);

        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Errore stream Nextcloud', [
                ...$this->pathContext($remotePath),
                'exception' => $e::class,
            ]);
            abort(500, 'Impossibile completare lo stream dal server Nextcloud.');
        }
    }

    public function mediaRoot(string $mediaKind): string
    {
        return match ($mediaKind) {
            'video' => config('services.nextcloud.videos_root', '/'),
            default => config('services.nextcloud.photos_root', '/'),
        };
    }

    /**
     * Predispone le directory foto e video dedicate a un cliente.
     *
     * @return array{photo: string, video: string}|null
     */
    public function ensureClientMediaDirectories(string $folderName): ?array
    {
        $paths = [
            'photo' => rtrim($this->mediaRoot('photo'), '/').'/'.$folderName,
            'video' => rtrim($this->mediaRoot('video'), '/').'/'.$folderName,
        ];

        foreach (array_unique($paths) as $path) {
            if (! $this->ensureDirectoryExists($path)) {
                return null;
            }
        }

        return $paths;
    }

    public function acquireLocksForPaths(array $paths, ?int $seconds = null): array
    {
        if ($seconds === null) {
            $seconds = max(120, count($paths) * 60);
        }

        $normalized = array_map(fn ($p) => $this->normalizePath($p), $paths);
        $unique = array_unique($normalized);
        sort($unique);

        $locks = [];
        try {
            foreach ($unique as $path) {
                $lockKey = 'nextcloud_share_lock_'.md5($path);
                $lock = Cache::lock($lockKey, $seconds);
                $lock->block(10);
                $locks[] = $lock;
            }

            return $locks;
        } catch (\Throwable $e) {
            $this->releaseLocks($locks);
            throw new NextcloudShareException('Timeout acquisizione lock sui path');
        }
    }

    public function releaseLocks(array $locks): void
    {
        foreach (array_reverse($locks) as $lock) {
            try {
                $lock?->release();
            } catch (\Throwable $e) {
                Log::warning('Failed to release lock', [
                    'exception' => $e::class,
                ]);
            }
        }
    }

    public function ensurePublicShare(string $path): NextcloudPublicShareResult
    {
        if (! $this->isConfigured()) {
            throw new NextcloudShareException('Nextcloud non è configurato.');
        }

        $path = $this->normalizePath($path);
        $url = $this->baseUrl.'/ocs/v2.php/apps/files_sharing/api/v1/shares';
        $headers = [
            'OCS-APIRequest' => 'true',
            'Accept' => 'application/json',
        ];
        try {
            // 1. GET (lookup)
            $checkResponse = null;
            try {
                $checkResponse = $this->idempotentRequest()
                    ->withHeaders($headers)
                    ->get($url, ['path' => $path]);
            } catch (RequestException $e) {
                if ($e->response && $e->response->status() === 404) {
                    $checkResponse = $e->response;
                } else {
                    throw new NextcloudShareException('Impossibile connettersi a Nextcloud per lookup share. HTTP: '.($e->response ? $e->response->status() : 'Unknown'));
                }
            }

            if ($checkResponse && ($checkResponse->successful() || $checkResponse->status() === 404)) {
                if ($checkResponse->successful()) {
                    $data = $checkResponse->json();
                    $statusCode = $data['ocs']['meta']['statuscode'] ?? 500;

                    if ($statusCode === 100 || $statusCode === 200) {
                        $shares = $data['ocs']['data'] ?? [];
                        if (is_array($shares)) {
                            if (isset($shares['id'])) {
                                $shares = [$shares];
                            }

                            foreach ($shares as $share) {
                                if (is_array($share) && isset($share['share_type'], $share['url'], $share['id']) && (int) $share['share_type'] === 3 && ! empty($share['url'])) {
                                    $expiration = $share['expiration'] ?? null;
                                    if (! $expiration || Carbon::parse($expiration)->isFuture()) {
                                        return new NextcloudPublicShareResult(
                                            $share['url'],
                                            $share['id'],
                                            false
                                        );
                                    }
                                }
                            }
                        }
                    } elseif ($statusCode !== 404) {
                        throw new NextcloudShareException('Errore OCS in lookup share. Status: '.$statusCode);
                    }
                }
            } else {
                throw new NextcloudShareException('Impossibile connettersi a Nextcloud per lookup share. HTTP: '.($checkResponse ? $checkResponse->status() : 'Unknown'));
            }

            // 2. POST (creazione)
            $payload = [
                'path' => $path,
                'shareType' => 3, // public link
                'permissions' => 1, // read
            ];

            $expireDays = config('services.nextcloud.share_expire_days', 7);
            if ($expireDays > 0) {
                $payload['expireDate'] = now()->addDays($expireDays)->toDateString();
            }

            // La creazione non viene ritentata automaticamente: un timeout può
            // avvenire dopo che Nextcloud ha già creato la share. Il tentativo
            // applicativo successivo la ritroverà tramite il lookup idempotente.
            $response = $this->request()
                ->withHeaders($headers)
                ->asForm()
                ->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $statusCode = $data['ocs']['meta']['statuscode'] ?? 500;

                if (($statusCode === 100 || $statusCode === 200) && isset($data['ocs']['data']['url'], $data['ocs']['data']['id'])) {
                    return new NextcloudPublicShareResult(
                        $data['ocs']['data']['url'],
                        $data['ocs']['data']['id'],
                        true
                    );
                }
                throw new NextcloudShareException('Errore OCS in creazione share. Status: '.$statusCode);
            }

            throw new NextcloudShareException('Impossibile connettersi a Nextcloud per creazione share. HTTP: '.$response->status());
        } catch (ConnectionException $e) {
            throw new NextcloudShareException(
                'Errore di connessione a Nextcloud.',
                0,
                $e
            );
        } catch (\Throwable $e) {
            if ($e instanceof NextcloudShareException) {
                throw $e;
            }
            throw new NextcloudShareException(
                'Errore inatteso durante la gestione della share Nextcloud.',
                0,
                $e
            );
        }
    }

    public function revokePublicShareById(string|int $shareId): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $url = $this->baseUrl.'/ocs/v2.php/apps/files_sharing/api/v1/shares/'.$shareId;
        $headers = [
            'OCS-APIRequest' => 'true',
            'Accept' => 'application/json',
        ];

        try {
            $response = null;
            try {
                $response = $this->idempotentRequest()
                    ->withHeaders($headers)
                    ->delete($url);
            } catch (RequestException $e) {
                if ($e->response && $e->response->status() === 404) {
                    return true;
                }
                throw $e;
            }

            if ($response && $response->status() === 404) {
                return true;
            }

            if ($response && $response->successful()) {
                $data = $response->json();
                if (! is_array($data)) {
                    Log::error('Nextcloud revoca fallita: la risposta non è un array JSON valido', [
                        'share_id_hash' => hash('sha256', (string) $shareId),
                        'status' => $response->status(),
                    ]);

                    return false;
                }
                $statusCode = $data['ocs']['meta']['statuscode'] ?? 500;

                if ($statusCode === 100 || $statusCode === 200 || $statusCode === 404) {
                    return true;
                }

                Log::error('Errore OCS revoca share Nextcloud: '.$statusCode);

                return false;
            }

            Log::error('Errore HTTP revoca share Nextcloud: '.($response ? $response->status() : 'Unknown'));

            return false;
        } catch (\Throwable $e) {
            Log::error('Nextcloud share revocation error', [
                'share_id_hash' => hash('sha256', (string) $shareId),
                'exception' => $e::class,
            ]);
            throw new NextcloudShareException(
                'Errore di revoca share Nextcloud.',
                0,
                $e
            );
        }
    }

    /**
     * Assicura che una directory esista su Nextcloud, creandola ricorsivamente se necessario.
     * Ritorna true se la directory esiste o è stata creata con successo.
     */
    public function ensureDirectoryExists(string $path): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $path = $this->normalizePath($path);
        $segments = array_filter(explode('/', trim($path, '/')));

        $currentPath = '';
        foreach ($segments as $segment) {
            $currentPath .= '/'.$segment;
            $url = rtrim($this->buildWebdavUrl($currentPath), '/').'/';

            try {
                // Verifica se esiste già
                $check = $this->idempotentRequest()
                    ->send('PROPFIND', $url, [
                        'headers' => ['Depth' => '0'],
                    ]);

                if ($check->status() === 207) {
                    continue; // Esiste già
                }

                $response = $this->idempotentRequest()
                    ->send('MKCOL', $url);

                $status = $response->status();

                if ($status !== 201 && $status !== 405) {
                    Log::error('Nextcloud MKCOL fallito', [
                        ...$this->pathContext($currentPath),
                        'status' => $status,
                    ]);

                    return false;
                }
            } catch (\Throwable $e) {
                Log::error('Errore Nextcloud WebDAV', [
                    ...$this->pathContext($currentPath),
                    'exception' => $e::class,
                ]);

                return false;
            }
        }

        return true;
    }

    private function buildWebdavUrl(string $path = '/'): string
    {
        $path = $this->normalizePath($path);

        $encodedPath = collect(explode('/', trim($path, '/')))
            ->filter()
            ->map(fn ($segment) => rawurlencode($segment))
            ->implode('/');

        return $this->baseUrl
            .'/'
            .trim($this->webdavPath, '/')
            .'/'
            .rawurlencode(trim($this->username, '/'))
            .($encodedPath ? '/'.$encodedPath : '');
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

        $path = '/'.ltrim($path, '/');
        // rimuove slash multipli
        $path = preg_replace('#/+#', '/', $path);

        return $path === '' ? '/' : $path;
    }

    /**
     * Extract a path only when the DAV href belongs to the configured user root.
     */
    private function ownedPathFromHref(string $href): string
    {
        $hrefPath = parse_url($href, PHP_URL_PATH);
        if (! is_string($hrefPath) || $hrefPath === '') {
            $hrefPath = $href;
        }

        $decodedHrefPath = rawurldecode($hrefPath);
        $ownershipRoot = '/'
            .trim($this->webdavPath, '/')
            .'/'
            .trim(rawurldecode($this->username), '/');

        if (
            $decodedHrefPath !== $ownershipRoot
            && ! str_starts_with($decodedHrefPath, $ownershipRoot.'/')
        ) {
            throw new NextcloudPermanentFailureException(
                'Nextcloud DAV response is outside the configured user root.'
            );
        }

        $relativePath = substr($decodedHrefPath, strlen($ownershipRoot));

        return $this->normalizePath($relativePath === '' ? '/' : $relativePath);
    }

    public function previewResponse(string $path, int $width = 900, int $height = 900)
    {
        $path = $this->normalizePath($path);

        // 1. Prova preview nativa Nextcloud
        $previewUrl = $this->baseUrl.'/index.php/core/preview.png';

        try {
            $preview = $this->idempotentRequest()
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
                ...$this->pathContext($path),
                'exception' => $e::class,
            ]);
        }

        // 2. Fallback sicuro: scarica il file via WebDAV
        $download = $this->idempotentRequest()
            ->get($this->buildWebdavUrl($path));

        abort_unless($download->successful(), 404);

        $contentType = $download->header('Content-Type', 'application/octet-stream');

        abort_unless(str_starts_with($contentType, 'image/'), 415);

        return response($download->body(), 200)
            ->header('Content-Type', $contentType)
            ->header('Cache-Control', 'private, max-age=600');
    }

    private function request(): PendingRequest
    {
        return Http::connectTimeout($this->connectTimeout)
            ->timeout($this->requestTimeout)
            ->withOptions(['allow_redirects' => false])
            ->withBasicAuth($this->username, $this->password);
    }

    private function idempotentRequest(): PendingRequest
    {
        return $this->request()->retry(
            3,
            300,
            function (\Throwable $exception): bool {
                if ($exception instanceof ConnectionException) {
                    return true;
                }

                if ($exception instanceof RequestException && $exception->response) {
                    return in_array(
                        $exception->response->status(),
                        [408, 425, 429],
                        true
                    ) || $exception->response->serverError();
                }

                return false;
            },
            throw: false
        );
    }

    private function pathContext(string $path): array
    {
        return [
            'path_hash' => hash('sha256', $path),
        ];
    }
}
