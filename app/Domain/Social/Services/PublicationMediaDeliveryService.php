<?php

namespace App\Domain\Social\Services;

use App\Domain\Social\DTOs\PublicationMediaDeliveryResult;
use App\Models\MarketingCampaignPostPublication;
use Illuminate\Support\Facades\URL;

class PublicationMediaDeliveryService
{
    public function deliver(MarketingCampaignPostPublication $publication): array
    {
        $payload = $publication->payload_snapshot;
        if (! is_array($payload) || ! isset($payload['media']) ||
            ! is_array($payload['media']) || ! array_is_list($payload['media'])) {
            return [
                new PublicationMediaDeliveryResult(
                    passed: false,
                    errors: ['Lo snapshot non contiene una lista media valida.']
                ),
            ];
        }

        $results = [];

        foreach ($payload['media'] as $index => $media) {
            $validationError = $this->validateDescriptor($media);
            if ($validationError !== null) {
                $results[] = new PublicationMediaDeliveryResult(
                    passed: false,
                    errors: ["Descrittore media {$index} non valido: {$validationError}"]
                );

                continue;
            }

            $url = URL::temporarySignedRoute(
                'public.social.publication-media.deliver',
                now()->addHours(24),
                [
                    'publication' => $publication->id,
                    'mediaIndex' => $index,
                    'hash' => $publication->snapshot_hash,
                ]
            );

            $results[] = new PublicationMediaDeliveryResult(
                passed: true,
                url: $url,
                diagnosticPayload: [
                    'generated_from_snapshot' => true,
                    'media_index' => $index,
                    'storage_source' => $media['storage_source'],
                ],
                type: strtolower($media['media_type'])
            );
        }

        return $results;
    }

    private function validateDescriptor(mixed $media): ?string
    {
        if (! is_array($media)) {
            return 'il descrittore deve essere un array';
        }

        foreach (['storage_source', 'mime_type', 'media_type', 'size_bytes'] as $field) {
            if (! array_key_exists($field, $media)) {
                return "campo {$field} mancante";
            }
        }

        if (! in_array($media['storage_source'], ['local', 'nextcloud'], true)) {
            return 'storage_source non supportato';
        }

        if (! is_string($media['mime_type']) || $media['mime_type'] === '' ||
            ! is_string($media['media_type']) || $media['media_type'] === '') {
            return 'mime_type o media_type non valido';
        }

        if (! is_int($media['size_bytes']) || $media['size_bytes'] <= 0) {
            return 'size_bytes deve essere un intero positivo';
        }

        $sourceFields = $media['storage_source'] === 'local'
            ? ['disk', 'path']
            : ['nextcloud_path'];

        foreach ($sourceFields as $field) {
            if (! isset($media[$field]) || ! is_string($media[$field]) ||
                $media[$field] === '') {
                return "campo {$field} mancante o non valido";
            }
        }

        return null;
    }
}
