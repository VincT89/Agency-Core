<?php

namespace App\Domain\Social\Services;

use App\Support\Http\ProviderErrorSanitizer;
use App\Support\Network\HostResolver;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ImageStagerService
{
    /**
     * Metadata calculated while each response is streamed to disk.
     *
     * @var array<string, array{size_bytes: int, sha256: string, mime_type: string}>
     */
    private array $temporaryMetadata = [];

    public function __construct(
        private readonly HostResolver $hostResolver
    ) {}

    /**
     * @param  array<int, string>|null  $urls
     * @return array<int, string> Temporary file paths
     */
    public function downloadAndValidate(?array $urls): array
    {
        if (empty($urls)) {
            return [];
        }

        $temporaryFiles = [];

        foreach ($urls as $url) {
            if (! is_string($url) || trim($url) === '') {
                $this->deleteTemporary($temporaryFiles);

                throw new RuntimeException('Image URL must be a non-empty string.');
            }

            try {
                $temporaryFiles[] = $this->downloadSingleImage($url);
            } catch (Throwable $exception) {
                $this->deleteTemporary($temporaryFiles);

                throw new RuntimeException(
                    'Download o validazione dell’immagine non riusciti: '
                    .ProviderErrorSanitizer::safeText(
                        $exception->getMessage()
                    ),
                    0,
                    $exception
                );
            }
        }

        return $temporaryFiles;
    }

    /**
     * @return array{size_bytes: int, sha256: string, mime_type: string}|null
     */
    public function temporaryMetadata(string $temporaryPath): ?array
    {
        return $this->temporaryMetadata[$temporaryPath] ?? null;
    }

    private function downloadSingleImage(string $url, int $redirectCount = 0): string
    {
        $maxRedirects = max(0, (int) config('n8n_images.max_redirects', 3));

        if ($redirectCount > $maxRedirects) {
            throw new RuntimeException('Too many redirects.');
        }

        $parts = $this->validatedUrlParts($url);
        $host = $parts['host'];
        $port = $parts['port'];
        $resolvedIp = $this->hostResolver->resolveAndValidatePublicHost($host);

        $options = [
            'allow_redirects' => false,
            'stream' => true,
        ];

        $resolvedAddressIsValid = filter_var(
            $resolvedIp,
            FILTER_VALIDATE_IP
        ) !== false;

        if (
            ! app()->environment('testing')
            && (! $resolvedAddressIsValid || ! defined('CURLOPT_RESOLVE'))
        ) {
            throw new RuntimeException(
                'A validated IP and cURL DNS pinning are required.'
            );
        }

        if (defined('CURLOPT_RESOLVE') && $resolvedAddressIsValid) {
            $pinnedIp = str_contains($resolvedIp, ':') ? "[{$resolvedIp}]" : $resolvedIp;
            $options['curl'] = [
                CURLOPT_RESOLVE => ["{$host}:{$port}:{$pinnedIp}"],
            ];
        }

        $response = Http::withOptions($options)
            ->connectTimeout((int) config('n8n_images.connect_timeout_seconds', 5))
            ->timeout((int) config('n8n_images.timeout_seconds', 15))
            ->get($url);

        if ($response->redirect()) {
            $location = trim((string) $response->header('Location'));

            if ($location === '') {
                throw new RuntimeException('Redirect location missing.');
            }

            return $this->downloadSingleImage(
                $this->resolveRedirectUrl($url, $location),
                $redirectCount + 1
            );
        }

        if (! $response->successful()) {
            throw new RuntimeException('HTTP error: '.$response->status());
        }

        return $this->streamResponseToTemporaryFile($response);
    }

    /**
     * @return array{host: string, port: int}
     */
    private function validatedUrlParts(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || empty($parts['host']) || empty($parts['scheme'])) {
            throw new RuntimeException('Invalid image URL.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('URL credentials are not allowed.');
        }

        $scheme = strtolower((string) $parts['scheme']);
        $allowedSchemes = array_map(
            'strtolower',
            (array) config('n8n_images.allowed_schemes', ['https'])
        );

        if (! in_array($scheme, $allowedSchemes, true)) {
            throw new RuntimeException("URL scheme not allowed: {$scheme}");
        }

        $port = isset($parts['port'])
            ? (int) $parts['port']
            : ($scheme === 'https' ? 443 : 80);
        $allowedPorts = array_map(
            'intval',
            (array) config('n8n_images.allowed_ports', [80, 443])
        );

        if (! in_array($port, $allowedPorts, true)) {
            throw new RuntimeException("URL port not allowed: {$port}");
        }

        return [
            'host' => strtolower(rtrim((string) $parts['host'], '.')),
            'port' => $port,
        ];
    }

    private function resolveRedirectUrl(string $sourceUrl, string $location): string
    {
        if (parse_url($location, PHP_URL_SCHEME) !== null) {
            return $location;
        }

        $source = parse_url($sourceUrl);
        if ($source === false || empty($source['scheme']) || empty($source['host'])) {
            throw new RuntimeException('Cannot resolve redirect URL.');
        }

        if (str_starts_with($location, '//')) {
            return $source['scheme'].':'.$location;
        }

        $authority = $source['scheme'].'://'.$source['host'];
        if (isset($source['port'])) {
            $authority .= ':'.$source['port'];
        }

        if (str_starts_with($location, '/')) {
            return $authority.$location;
        }

        $sourcePath = $source['path'] ?? '/';
        $basePath = str_ends_with($sourcePath, '/')
            ? $sourcePath
            : dirname($sourcePath).'/';

        return $authority.$this->normalizeRedirectPath($basePath.$location);
    }

    private function normalizeRedirectPath(string $path): string
    {
        $query = '';
        if (($queryPosition = strpos($path, '?')) !== false) {
            $query = substr($path, $queryPosition);
            $path = substr($path, 0, $queryPosition);
        }

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return '/'.implode('/', $segments).$query;
    }

    private function streamResponseToTemporaryFile(Response $response): string
    {
        $maxBytes = max(1, (int) config('n8n_images.max_bytes', 15 * 1024 * 1024));
        $contentLength = $response->header('Content-Length');

        if (is_numeric($contentLength) && (int) $contentLength > $maxBytes) {
            throw new RuntimeException('File too large based on Content-Length.');
        }

        $partialPath = 'temp/n8n_images/'.Str::uuid().'.part';
        $disk = Storage::disk('local');
        $disk->makeDirectory(dirname($partialPath));
        $absolutePartialPath = $disk->path($partialPath);
        $target = @fopen($absolutePartialPath, 'w+b');

        if ($target === false) {
            throw new RuntimeException('Unable to create temporary file.');
        }

        $source = $response->toPsrResponse()->getBody();
        $hash = hash_init('sha256');
        $size = 0;
        $chunkSize = max(1024, (int) config('n8n_images.chunk_size_bytes', 64 * 1024));

        try {
            while (! $source->eof()) {
                $chunk = $source->read($chunkSize);
                if ($chunk === '') {
                    break;
                }

                $size += strlen($chunk);
                if ($size > $maxBytes) {
                    throw new RuntimeException('File too large based on streamed body size.');
                }

                hash_update($hash, $chunk);

                if (fwrite($target, $chunk) !== strlen($chunk)) {
                    throw new RuntimeException('Unable to write complete temporary file.');
                }
            }
        } catch (Throwable $exception) {
            fclose($target);
            $source->close();
            $disk->delete($partialPath);

            throw $exception;
        }

        fclose($target);
        $source->close();

        if ($size === 0) {
            $disk->delete($partialPath);

            throw new RuntimeException('Downloaded file is empty.');
        }

        try {
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($absolutePartialPath);
            $allowedMimes = (array) config('n8n_images.allowed_mime_types', []);

            if (! is_string($mime) || ! array_key_exists($mime, $allowedMimes)) {
                throw new RuntimeException('MIME type not allowed: '.($mime ?: 'unknown'));
            }

            $imageInfo = @getimagesize($absolutePartialPath);
            if ($imageInfo === false) {
                throw new RuntimeException('Downloaded payload is not a valid image.');
            }

            $pixelCount = (int) $imageInfo[0] * (int) $imageInfo[1];
            $maxPixels = max(1, (int) config('n8n_images.max_pixels', 40_000_000));
            if ($pixelCount > $maxPixels) {
                throw new RuntimeException('Image dimensions exceed the configured pixel limit.');
            }

            $finalPath = Str::beforeLast($partialPath, '.part')
                .'.'.$allowedMimes[$mime];

            if (! $disk->move($partialPath, $finalPath)) {
                throw new RuntimeException('Unable to finalize temporary file.');
            }

            $this->temporaryMetadata[$finalPath] = [
                'size_bytes' => $size,
                'sha256' => hash_final($hash),
                'mime_type' => $mime,
            ];

            return $finalPath;
        } catch (Throwable $exception) {
            $disk->delete($partialPath);

            throw $exception;
        }
    }

    /**
     * @param  array<int, string>  $temporaryPaths
     * @return array<int, string> Promoted file paths
     */
    public function promote(array $temporaryPaths): array
    {
        $promotedFiles = [];

        foreach ($temporaryPaths as $tempPath) {
            $filename = basename($tempPath);
            $finalPath = 'marketing_campaigns/posts/'.date('Y/m').'/'.$filename;

            try {
                if (! Storage::disk('local')->exists($tempPath)) {
                    throw new RuntimeException("Temporary file missing: {$tempPath}");
                }

                $stream = Storage::disk('local')->readStream($tempPath);
                if ($stream === false) {
                    throw new RuntimeException("Unable to read temporary file: {$tempPath}");
                }

                try {
                    if (! Storage::disk('social_media')->writeStream($finalPath, $stream)) {
                        throw new RuntimeException("Unable to promote temporary file: {$tempPath}");
                    }
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }

                $promotedFiles[] = $finalPath;
            } catch (Throwable $exception) {
                $this->deletePromoted($promotedFiles);

                throw $exception;
            }
        }

        return $promotedFiles;
    }

    /**
     * @param  array<int, string>  $temporaryPaths
     */
    public function deleteTemporary(array $temporaryPaths): void
    {
        foreach ($temporaryPaths as $path) {
            Storage::disk('local')->delete($path);
            unset($this->temporaryMetadata[$path]);
        }
    }

    /**
     * @param  array<int, string>  $promotedPaths
     */
    public function deletePromoted(array $promotedPaths): void
    {
        foreach ($promotedPaths as $path) {
            Storage::disk('social_media')->delete($path);
        }
    }
}
