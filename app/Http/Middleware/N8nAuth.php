<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class N8nAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthorized: Token missing',
            ], 401);
        }

        if ($token !== config('services.n8n.token')) {
            return response()->json([
                'ok' => false,
                'message' => 'Forbidden: Invalid token',
            ], 403);
        }

        return $next($request);
    }
}
