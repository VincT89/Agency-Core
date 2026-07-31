<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ArubaCallbackAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.aruba_einvoicing.callback_key', '');

        if (strlen($expected) < 32) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Callback non configurata.',
            ], 503);
        }

        $provided = trim((string) $request->header('Authorization', ''));
        $bearer = str_starts_with(strtolower($provided), 'bearer ')
            ? trim(substr($provided, 7))
            : null;
        $authorized = ($provided !== '' && hash_equals($expected, $provided))
            || ($bearer !== null && hash_equals($expected, $bearer));

        if (! $authorized) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Richiesta non autorizzata.',
            ], 401);
        }

        return $next($request);
    }
}
