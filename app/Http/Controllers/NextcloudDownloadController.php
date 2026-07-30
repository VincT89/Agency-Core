<?php

namespace App\Http\Controllers;

use App\Services\Integrations\Nextcloud\NextcloudPathAuthorizer;
use App\Services\Integrations\Nextcloud\NextcloudService;
use Illuminate\Http\Request;

class NextcloudDownloadController extends Controller
{
    public function __invoke(
        Request $request,
        NextcloudService $nextcloud,
        NextcloudPathAuthorizer $pathAuthorizer
    ) {
        $path = $request->query('path');
        abort_unless(is_string($path) && $path !== '', 404);

        $path = $nextcloud->normalizePath($path);

        abort_unless(
            $pathAuthorizer->canAccess($request->user(), $path),
            403,
            'Percorso non autorizzato.'
        );

        return $nextcloud->streamFileResponse($path, $request);
    }
}
