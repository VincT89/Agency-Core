<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class N8nAuth
{
    // Valida il token bearer per le richieste in ingresso da n8n
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthorized: Token missing',
            ], 401);
        }

        $expected = config('services.n8n.token');

        if (! $expected || ! hash_equals($expected, $token)) {
            return response()->json([
                'ok' => false,
                'message' => 'Forbidden: Invalid token',
            ], 403);
        }

        return $next($request);
    }
}
