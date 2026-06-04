<?php

namespace App\Http\Controllers;

use App\Models\MarketingCampaignPostMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SocialMediaDeliveryController extends Controller
{
    public function __invoke(Request $request, MarketingCampaignPostMedia $media)
    {
        $disk = $media->disk ?: ($media->source === 'local' ? 'public' : null);
        $path = $media->path;

        if (!$disk) {
            Log::warning("Provider Delivery: Media senza disk configurato", [
                'media_id' => $media->id,
                'ip' => $request->ip(),
            ]);
            abort(404, 'Media non configurato per la delivery.');
        }

        if (empty($path)) {
            Log::warning("Provider Delivery: Media senza path richiesto", [
                'media_id' => $media->id,
                'ip' => $request->ip(),
            ]);
            abort(404, 'Media non configurato o path assente.');
        }

        // Bloccare path traversal (se path contiene ../)
        if (str_contains($path, '../') || str_contains($path, '..\\')) {
            Log::warning("Provider Delivery: Tentativo path traversal", [
                'media_id' => $media->id,
                'path_hash' => hash('sha256', $path),
                'ip' => $request->ip(),
            ]);
            abort(403, 'Path non valido.');
        }

        if (!Storage::disk($disk)->exists($path)) {
            Log::warning("Provider Delivery: File fisico non trovato", [
                'media_id' => $media->id,
                'disk' => $disk,
                'ip' => $request->ip(),
            ]);
            abort(404, 'File non trovato.');
        }

        Log::info("Provider Delivery: Accesso al media autorizzato", [
            'media_id' => $media->id,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'range' => $request->header('Range'),
            'status' => 200,
            'mime' => $media->mime_type,
        ]);

        // Utilizziamo response()->file() che si appoggia a BinaryFileResponse di Symfony,
        // garantendo un supporto perfetto per 206 Partial Content e request 'Range'.
        $absolutePath = Storage::disk($disk)->path($path);
        
        // Soft Hardening: usiamo il vero MIME invece di fidarci del db
        try {
            $realMime = Storage::disk($disk)->mimeType($path);
        } catch (\Exception $e) {
            $realMime = explode(';', $media->mime_type)[0] ?? 'application/octet-stream';
        }

        $headers = [];
        if ($realMime) {
            $headers['Content-Type'] = $realMime;
        }

        return response()->file($absolutePath, $headers);
    }
}
