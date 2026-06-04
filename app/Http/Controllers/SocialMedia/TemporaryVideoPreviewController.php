<?php

namespace App\Http\Controllers\SocialMedia;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;

class TemporaryVideoPreviewController extends Controller
{
    public function __invoke(Request $request, string $filename)
    {
        abort_unless($request->hasValidSignature(), 401);

        if (str_contains($filename, "/") || str_contains($filename, "\\")) {
            abort(403);
        }

        $storage = FileUploadConfiguration::storage();
        $relativePath = FileUploadConfiguration::path($filename, false);

        abort_unless($storage->exists($relativePath), 404);

        $absolutePath = $storage->path($relativePath);
        $mimeType = $storage->mimeType($relativePath) ?: "video/mp4";

        return response()->file($absolutePath, [
            "Content-Type" => $mimeType,
            "Accept-Ranges" => "bytes",
            "Cache-Control" => "private, max-age=300",
        ]);
    }
}

