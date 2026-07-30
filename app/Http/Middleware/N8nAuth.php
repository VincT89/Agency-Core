<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class N8nAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthorized: Token missing',
            ], 401);
        }

        $expected = (string) config('services.n8n.token', '');

        if ($expected === '' || ! hash_equals($expected, $token)) {
            return response()->json([
                'ok' => false,
                'message' => 'Forbidden: Invalid token',
            ], 403);
        }

        $signingSecret = (string) config('services.n8n.signing_secret', '');
        $signatureRequired = (bool) config(
            'services.n8n.require_signature',
            app()->isProduction()
        );

        if ($signatureRequired && strlen($expected) < 32) {
            return response()->json([
                'ok' => false,
                'message' => 'Integration authentication is not configured securely.',
            ], 503);
        }

        if ($signingSecret === '') {
            if ($signatureRequired) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Integration signing is not configured.',
                ], 503);
            }

            return $next($request);
        }

        if (strlen($signingSecret) < 32) {
            return response()->json([
                'ok' => false,
                'message' => 'Integration signing is not configured securely.',
            ], 503);
        }

        $timestamp = $request->header('X-N8N-Timestamp');
        $signature = $request->header('X-N8N-Signature');

        if (! is_string($timestamp) || preg_match('/^\d{10}$/', $timestamp) !== 1
            || ! is_string($signature) || $signature === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Forbidden: Missing request signature',
            ], 403);
        }

        $maxClockSkew = max(
            30,
            (int) config('services.n8n.signature_max_clock_skew_seconds', 300)
        );

        if (abs(now()->timestamp - (int) $timestamp) > $maxClockSkew) {
            return response()->json([
                'ok' => false,
                'message' => 'Forbidden: Expired request signature',
            ], 403);
        }

        $providedSignature = str_starts_with($signature, 'sha256=')
            ? substr($signature, 7)
            : $signature;
        $expectedSignature = hash_hmac(
            'sha256',
            $this->signaturePayload($request, $timestamp),
            $signingSecret
        );

        if (preg_match('/^[a-f0-9]{64}$/i', $providedSignature) !== 1
            || ! hash_equals($expectedSignature, strtolower($providedSignature))) {
            return response()->json([
                'ok' => false,
                'message' => 'Forbidden: Invalid request signature',
            ], 403);
        }

        $replayKey = 'n8n:request-signature:'.hash(
            'sha256',
            $timestamp.'.'.$providedSignature
        );

        if (! Cache::add($replayKey, true, now()->addSeconds($maxClockSkew * 2))) {
            return response()->json([
                'ok' => false,
                'message' => 'Conflict: Request signature already used',
            ], 409);
        }

        return $next($request);
    }

    private function signaturePayload(
        Request $request,
        string $timestamp
    ): string {
        $requestTarget = $request->getRequestUri();

        return implode("\n", [
            $timestamp,
            strtoupper($request->method()),
            $requestTarget !== '' ? $requestTarget : '/'.$request->path(),
            $request->getContent(),
        ]);
    }
}
