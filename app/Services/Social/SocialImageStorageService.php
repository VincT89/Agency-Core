<?php

namespace App\Services\Social;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class SocialImageStorageService
{
    // Scarica un'immagine da un URL e la salva nello storage locale
    public function downloadAndStore(string $url, string $disk = 'public'): string
    {
        $response = Http::timeout(15)->get($url);

        if (! $response->successful()) {
            throw new Exception("Impossibile scaricare l'immagine da n8n. Status: " . $response->status());
        }

        // Determiniamo l'estensione dal content type
        $contentType = $response->header('Content-Type');

        if (! str_starts_with((string) $contentType, 'image/')) {
            abort(422, 'Invalid image content type: ' . $contentType);
        }

        $extension = $this->getExtensionFromContentType($contentType);

        $filename = 'social-posts/' . Str::uuid()->toString() . '.' . $extension;

        Storage::disk($disk)->put($filename, $response->body());

        return $filename;
    }

    // Deduce l'estensione dal Content-Type
    protected function getExtensionFromContentType(?string $contentType): string
    {
        return match ($contentType) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg', // Fallback standard (jpeg)
        };
    }
}
