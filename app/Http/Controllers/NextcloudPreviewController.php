<?php

namespace App\Http\Controllers;

use App\Services\Integrations\Nextcloud\NextcloudPathAuthorizer;
use App\Services\Integrations\Nextcloud\NextcloudService;
use Illuminate\Http\Request;

class NextcloudPreviewController extends Controller
{
    public function __invoke(
        Request $request,
        NextcloudService $nextcloud,
        NextcloudPathAuthorizer $pathAuthorizer
    ) {
        $path = $request->query('path');
        $width = min(max((int) $request->query('w', 800), 100), 800);
        $height = min(max((int) $request->query('h', 800), 100), 800);

        abort_unless($path, 404);

        $path = $nextcloud->normalizePath($path);

        abort_unless(
            $pathAuthorizer->canAccess($request->user(), $path),
            403,
            'Percorso non autorizzato.'
        );

        return $nextcloud->previewResponse($path, $width, $height);
    }
}
