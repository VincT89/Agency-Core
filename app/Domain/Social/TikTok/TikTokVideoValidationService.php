<?php

namespace App\Domain\Social\TikTok;

use App\Models\MarketingCampaignPostMedia;
use Illuminate\Support\Facades\Log;

class TikTokVideoValidationService
{
    /**
     * Valida i limiti stringenti imposti da TikTok Content Posting API.
     * 
     * @param MarketingCampaignPostMedia $media
     * @return array{isValid: bool, error: ?string}
     */
    public function validate(MarketingCampaignPostMedia $media): array
    {
        if (!$media->isVideo()) {
            return [
                'isValid' => false,
                'error' => 'TikTok supporta esclusivamente contenuti video. Immagini o altri formati non sono ammessi.'
            ];
        }

        $metadata = $media->file_metadata ?? [];

        // Verifica MIME type (solo mp4 o webm ammessi comunemente per TikTok via API)
        $mime = $media->mime_type ?? ($metadata['mime_type'] ?? null);
        $allowedMimes = ['video/mp4', 'video/webm', 'video/quicktime'];
        if ($mime && !in_array($mime, $allowedMimes)) {
            Log::warning("TikTok validation fallita: Mime type non supportato", ['mime' => $mime]);
            return [
                'isValid' => false,
                'error' => "Formato video non supportato ($mime). TikTok richiede MP4 o WebM."
            ];
        }

        // Verifica dimensione file (es. massimo 4GB, ma teniamo un limite ragionevole di 500MB per draft)
        $size = $media->source_size_bytes ?? ($metadata['size'] ?? 0);
        $maxSizeBytes = 500 * 1024 * 1024; // 500MB
        if ($size > $maxSizeBytes) {
            return [
                'isValid' => false,
                'error' => 'Il file video supera il limite di 500MB consentito per l\'upload.'
            ];
        }

        // Verifica durata (API richiede tipicamente da 3 a 600 secondi)
        if (isset($metadata['duration'])) {
            $duration = (float) $metadata['duration'];
            if ($duration < 3 || $duration > 600) {
                return [
                    'isValid' => false,
                    'error' => 'La durata del video deve essere compresa tra 3 secondi e 10 minuti.'
                ];
            }
        }

        // TODO: Aggiungere in futuro controlli su codec (H.264/H.265) e aspect ratio
        // se disponibili nei metadati estratti da FFMpeg.

        return [
            'isValid' => true,
            'error' => null
        ];
    }
}
