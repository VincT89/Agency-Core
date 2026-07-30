<?php

namespace App\Domain\Social\TikTok;

use Illuminate\Support\Collection;
use App\Models\MarketingCampaignPostMedia;
use Illuminate\Support\Facades\Log;

class TikTokPhotoValidationService
{
    /**
     * Valida i requisiti stringenti imposti da TikTok Content Posting API per i photo carousel.
     * 
     * @param Collection|MarketingCampaignPostMedia[] $mediaCollection
     * @param int $maxCapabilityCount
     * @return array{isValid: bool, error: ?string}
     */
    public function validate(Collection $mediaCollection, int $maxCapabilityCount): array
    {
        if ($mediaCollection->isEmpty()) {
            return [
                'isValid' => false,
                'error' => 'Nessun media fornito per la pubblicazione.'
            ];
        }

        // 1. Limite massimo di foto
        $maxPhotos = min(config('services.tiktok.max_photo_count', 10), $maxCapabilityCount);
        if ($mediaCollection->count() > $maxPhotos) {
            return [
                'isValid' => false,
                'error' => "Il numero di foto ({$mediaCollection->count()}) supera il limite massimo consentito per TikTok ({$maxPhotos})."
            ];
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSizeBytes = 20 * 1024 * 1024; // TikTok usually limits images to ~20MB

        foreach ($mediaCollection as $index => $media) {
            // 2. Controllo media_type (solo immagini)
            if ($media->isVideo() || $media->media_type === 'video') {
                return [
                    'isValid' => false,
                    'error' => "Il file alla posizione " . ($index + 1) . " è un video. TikTok non supporta slideshow misti con foto e video."
                ];
            }

            $metadata = $media->file_metadata ?? [];

            // 3. Controllo formato (MIME)
            $mime = $media->mime_type ?? ($metadata['mime_type'] ?? 'image/jpeg');
            if (!in_array($mime, $allowedMimes)) {
                Log::warning("TikTok Photo validation fallita: Mime type non supportato", ['mime' => $mime, 'media_id' => $media->id]);
                return [
                    'isValid' => false,
                    'error' => "Il formato dell'immagine non è supportato ($mime). TikTok richiede JPEG, PNG o WebP."
                ];
            }

            // 4. Controllo dimensione file
            $size = $media->source_size_bytes ?? ($metadata['size'] ?? 0);
            if ($size > $maxSizeBytes) {
                return [
                    'isValid' => false,
                    'error' => "L'immagine alla posizione " . ($index + 1) . " supera il limite di 20MB."
                ];
            }
        }

        return [
            'isValid' => true,
            'error' => null
        ];
    }
}
