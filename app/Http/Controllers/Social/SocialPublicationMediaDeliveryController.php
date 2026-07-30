<?php

namespace App\Http\Controllers\Social;

use App\Exceptions\Social\NextcloudFileNotFoundException;
use App\Exceptions\Social\NextcloudPermanentFailureException;
use App\Exceptions\Social\NextcloudTemporaryUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\MarketingCampaignPostPublication;
use App\Services\Integrations\Nextcloud\NextcloudService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class SocialPublicationMediaDeliveryController extends Controller
{
    public function deliver(
        Request $request,
        int $publication,
        int $mediaIndex
    ): Response {
        abort_unless(
            $request->hasValidSignature(),
            403,
            'Invalid or expired signature.'
        );

        $publicationModel = MarketingCampaignPostPublication::findOrFail($publication);
        $requestedHash = $request->query('hash');

        abort_unless(
            is_string($requestedHash) &&
            is_string($publicationModel->snapshot_hash) &&
            hash_equals($publicationModel->snapshot_hash, $requestedHash),
            403,
            'Snapshot hash mismatch. Media might have been altered.'
        );

        $payload = $publicationModel->payload_snapshot;
        abort_unless(
            is_array($payload) &&
            isset($payload['media']) &&
            is_array($payload['media']),
            400,
            'Invalid media snapshot.'
        );
        abort_unless(
            array_key_exists($mediaIndex, $payload['media']),
            404,
            'Media item not found in snapshot.'
        );

        $media = $payload['media'][$mediaIndex];
        abort_unless(is_array($media), 400, 'Invalid media descriptor.');

        foreach (['storage_source', 'mime_type', 'size_bytes'] as $field) {
            abort_unless(
                array_key_exists($field, $media),
                400,
                "Missing {$field} in media snapshot."
            );
        }

        abort_unless(
            is_string($media['mime_type']) && $media['mime_type'] !== '',
            400,
            'Invalid MIME type in media snapshot.'
        );
        abort_unless(
            is_int($media['size_bytes']) && $media['size_bytes'] > 0,
            400,
            'Invalid size_bytes in media snapshot.'
        );

        return match ($media['storage_source']) {
            'local' => $this->deliverLocal(
                $request,
                $media,
                $media['size_bytes'],
                $media['mime_type']
            ),
            'nextcloud' => $this->deliverNextcloud($request, $media),
            default => abort(400, 'Unsupported storage source.'),
        };
    }

    private function deliverLocal(
        Request $request,
        array $media,
        int $size,
        string $mimeType
    ): Response {
        $disk = $media['disk'] ?? null;
        $path = $media['path'] ?? null;

        abort_unless(
            is_string($disk) && $disk !== '' &&
            is_string($path) && $path !== '',
            404,
            'Local media descriptor is incomplete.'
        );
        $expectedHash = $media['sha256'] ?? null;
        abort_unless(
            is_string($expectedHash)
            && preg_match('/^[a-f0-9]{64}$/', $expectedHash) === 1,
            400,
            'Local media checksum is missing or malformed.'
        );

        try {
            abort_unless(
                Storage::disk($disk)->exists($path),
                404,
                'Local media file not found.'
            );

            abort_unless(
                Storage::disk($disk)->size($path) === $size,
                409,
                'Local media size no longer matches the frozen snapshot.'
            );

            $integrityStream = Storage::disk($disk)->readStream($path);
            abort_unless(
                is_resource($integrityStream),
                503,
                'Local media integrity stream is temporarily unavailable.'
            );

            try {
                $hashContext = hash_init('sha256');
                hash_update_stream($hashContext, $integrityStream);
                $actualHash = hash_final($hashContext);
            } finally {
                fclose($integrityStream);
            }

            abort_unless(
                hash_equals($expectedHash, $actualHash),
                409,
                'Local media checksum no longer matches the frozen snapshot.'
            );

            $stream = Storage::disk($disk)->readStream($path);
        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('Impossibile aprire il media locale per il delivery social', [
                'publication_media_disk' => $disk,
                'error' => $e->getMessage(),
            ]);
            abort(503, 'Local media storage is temporarily unavailable.');
        }

        if (! is_resource($stream)) {
            abort(503, 'Local media stream is temporarily unavailable.');
        }

        return $this->streamWithRange($request, $stream, $size, $mimeType);
    }

    private function deliverNextcloud(Request $request, array $media): Response
    {
        $path = $media['nextcloud_path'] ?? null;
        $fileId = $media['nextcloud_file_id'] ?? null;
        $etag = $media['nextcloud_etag'] ?? null;
        $size = $media['size_bytes'] ?? null;
        $mimeType = $media['mime_type'] ?? null;
        abort_unless(
            is_string($path) && $path !== ''
            && is_string($fileId) && $fileId !== ''
            && is_string($etag) && $etag !== '',
            404,
            'Nextcloud media descriptor is incomplete.'
        );

        $nextcloud = app(NextcloudService::class);

        try {
            $fileInfo = $nextcloud->getFileInfo($path);
        } catch (NextcloudFileNotFoundException) {
            abort(404, 'Nextcloud media file not found.');
        } catch (NextcloudTemporaryUnavailableException) {
            abort(503, 'Nextcloud is temporarily unavailable.');
        } catch (NextcloudPermanentFailureException) {
            abort(409, 'Nextcloud media metadata is no longer valid.');
        }

        abort_unless(
            $fileInfo->fileId === $fileId
            && $fileInfo->etag === $etag
            && $fileInfo->sizeBytes === $size
            && $fileInfo->mimeType === $mimeType,
            409,
            'Nextcloud media no longer matches the frozen snapshot.'
        );

        return $nextcloud->streamFileResponse(
            $path,
            $request,
            $etag
        );
    }

    private function streamWithRange(
        Request $request,
        $stream,
        int $size,
        string $mimeType
    ): Response {
        $headers = [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, max-age=86400',
            'Accept-Ranges' => 'bytes',
        ];
        $range = $request->header('Range');

        if ($range === null) {
            $headers['Content-Length'] = (string) $size;

            if ($request->isMethod('HEAD')) {
                fclose($stream);

                return response('', 200, $headers);
            }

            return response()->stream(function () use ($stream, $size) {
                $this->copyExactBytes($stream, $size);
            }, 200, $headers);
        }

        if (preg_match('/^bytes=(?:(\d+)-(\d*)|-(\d+))$/', $range, $matches) !== 1) {
            fclose($stream);

            return response(
                'Requested Range Not Satisfiable',
                416,
                ['Content-Range' => "bytes */{$size}"]
            );
        }

        if (($matches[3] ?? '') !== '') {
            $suffixLength = (int) $matches[3];
            $start = max(0, $size - $suffixLength);
            $end = $size - 1;
        } else {
            $start = (int) $matches[1];
            $end = ($matches[2] ?? '') === ''
                ? $size - 1
                : (int) $matches[2];
        }

        if ($start > $end || $start >= $size || $end >= $size) {
            fclose($stream);

            return response(
                'Requested Range Not Satisfiable',
                416,
                ['Content-Range' => "bytes */{$size}"]
            );
        }

        if (fseek($stream, $start, SEEK_SET) !== 0) {
            fclose($stream);

            return response(
                'Media stream is not seekable',
                503,
                ['Retry-After' => '60']
            );
        }

        $length = $end - $start + 1;
        $headers['Content-Length'] = (string) $length;
        $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";

        if ($request->isMethod('HEAD')) {
            fclose($stream);

            return response('', 206, $headers);
        }

        return response()->stream(function () use ($stream, $length) {
            $this->copyExactBytes($stream, $length);
        }, 206, $headers);
    }

    private function copyExactBytes($stream, int $expectedBytes): void
    {
        $remaining = $expectedBytes;

        try {
            while ($remaining > 0 && ! feof($stream)) {
                $chunk = fread($stream, min(8192, $remaining));

                if ($chunk === false || $chunk === '') {
                    break;
                }

                echo $chunk;
                $remaining -= strlen($chunk);
            }

            if ($remaining !== 0) {
                Log::error('Social media stream ended before the declared length', [
                    'expected_bytes' => $expectedBytes,
                    'missing_bytes' => $remaining,
                ]);
            }
        } finally {
            fclose($stream);
        }
    }
}
