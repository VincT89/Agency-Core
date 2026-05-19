<?php

namespace App\Services;

use App\Models\TemporaryMediaUpload;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class SocialMediaPublicUrlService
{
    /**
     * Prende il contenuto del file (da public, s3 o Nextcloud) e lo copia 
     * in una cartella pubblica temporanea deduplicata per hash.
     * Ritorna l'URL pubblico.
     */
    public function getPublicUrl(
        string $sourcePath, 
        string $fileContent, 
        string $extension,
        ?int $postId = null,
        ?string $correlationId = null
    ): string {
        $hash = sha1($fileContent);
        $fileName = "{$hash}.{$extension}";
        $tempPath = "social-temp/{$fileName}";

        // Deduplica: Se esiste già nello storage, usiamo quello.
        if (!Storage::disk('public')->exists($tempPath)) {
            Storage::disk('public')->put($tempPath, $fileContent);
        }

        // Registriamo comunque il riferimento nella tabella per gestire il lifecycle
        TemporaryMediaUpload::create([
            'source_path' => $sourcePath,
            'temp_path' => $tempPath,
            'hash' => $hash,
            'marketing_campaign_post_id' => $postId,
            'correlation_id' => $correlationId,
            'cleanup_status' => 'pending',
        ]);

        // Usa env() per garantire un url assoluto valido (specialmente in locale per ngrok/herd)
        return url(Storage::url($tempPath));
    }
}
