<?php

namespace App\Http\Controllers;

use App\Services\Integrations\Nextcloud\NextcloudService;
use Illuminate\Http\Request;

class NextcloudPreviewController extends Controller
{
    public function __invoke(Request $request, NextcloudService $nextcloud)
    {
        $path = $request->query('path');
        $width = min(max((int) $request->query('w', 800), 100), 800);
        $height = min(max((int) $request->query('h', 800), 100), 800);

        abort_unless($path, 404);

        $path = $nextcloud->normalizePath($path);

        $photosRoot = rtrim(config('services.nextcloud.photos_root', '/Photos'), '/');
        $videosRoot = rtrim(config('services.nextcloud.videos_root', '/Videos'), '/');

        abort_unless(
            $path === $photosRoot
            || str_starts_with($path, $photosRoot . '/')
            || $path === $videosRoot
            || str_starts_with($path, $videosRoot . '/'),
            403
        );

        return $nextcloud->previewResponse($path, $width, $height);
    }
}
