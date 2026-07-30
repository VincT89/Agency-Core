<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class N8nIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $key = (string) $request->header('Idempotency-Key', '');
        $required = (bool) config(
            'services.n8n.require_idempotency_key',
            app()->isProduction()
        );

        if ($key === '') {
            if ($required) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Idempotency-Key header is required.',
                ], 400);
            }

            return $next($request);
        }

        if (preg_match('/\A[\x21-\x7E]{8,255}\z/', $key) !== 1) {
            return response()->json([
                'ok' => false,
                'message' => 'Idempotency-Key header is invalid.',
            ], 400);
        }

        $keyHash = hash('sha256', $key);
        $requestHash = hash(
            'sha256',
            $request->method()."\n".$request->getRequestUri()."\n".$request->getContent()
        );
        $lock = Cache::lock(
            'n8n:idempotency:'.$keyHash,
            max(
                1,
                (int) config('services.n8n.idempotency_lock_seconds', 600)
            )
        );

        try {
            return $lock->block(
                max(
                    1,
                    (int) config(
                        'services.n8n.idempotency_lock_wait_seconds',
                        5
                    )
                ),
                function () use (
                    $request,
                    $next,
                    $keyHash,
                    $requestHash
                ): Response {
                    $reservation = $this->reserveOrReplay(
                        $request,
                        $keyHash,
                        $requestHash
                    );

                    if ($reservation instanceof Response) {
                        return $reservation;
                    }

                    return $this->processReservedRequest(
                        $request,
                        $next,
                        $reservation
                    );
                }
            );
        } catch (LockTimeoutException) {
            return response()->json([
                'ok' => false,
                'message' => 'A request with this Idempotency-Key is in progress.',
            ], 409);
        }
    }

    private function reserveOrReplay(
        Request $request,
        string $keyHash,
        string $requestHash
    ): Response|int {
        return DB::transaction(function () use (
            $request,
            $keyHash,
            $requestHash
        ): Response|int {
            $existing = DB::table('integration_idempotency_keys')
                ->where('provider', 'n8n')
                ->where('key_hash', $keyHash)
                ->lockForUpdate()
                ->first();

            $staleAfter = now()->subMinutes(
                max(
                    1,
                    (int) config(
                        'services.n8n.idempotency_in_progress_timeout_minutes',
                        30
                    )
                )
            );

            if (
                $existing
                && Carbon::parse($existing->expires_at)->isPast()
            ) {
                DB::table('integration_idempotency_keys')
                    ->where('id', $existing->id)
                    ->delete();
                $existing = null;
            }

            if ($existing) {
                if (! hash_equals($existing->request_hash, $requestHash)) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'Idempotency-Key was used for a different request.',
                    ], 409);
                }

                if (
                    $existing->completed_at === null
                    && Carbon::parse($existing->created_at)->lte($staleAfter)
                ) {
                    DB::table('integration_idempotency_keys')
                        ->where('id', $existing->id)
                        ->delete();
                    $existing = null;
                }
            }

            if ($existing) {
                if ($existing->completed_at === null) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'A request with this Idempotency-Key is in progress.',
                    ], 409);
                }

                return response(
                    $existing->response_body ?? '',
                    (int) $existing->status_code,
                    [
                        'Content-Type' => $existing->content_type
                            ?: 'application/json',
                        'X-Idempotent-Replay' => 'true',
                    ]
                );
            }

            return DB::table('integration_idempotency_keys')->insertGetId([
                'provider' => 'n8n',
                'key_hash' => $keyHash,
                'request_hash' => $requestHash,
                'route' => $request->path(),
                'expires_at' => now()->addHours(
                    max(
                        1,
                        (int) config(
                            'services.n8n.idempotency_ttl_hours',
                            48
                        )
                    )
                ),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    private function processReservedRequest(
        Request $request,
        Closure $next,
        int $recordId
    ): Response {
        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            DB::table('integration_idempotency_keys')
                ->where('id', $recordId)
                ->delete();

            throw $exception;
        }

        if ($response->getStatusCode() >= 500) {
            DB::table('integration_idempotency_keys')
                ->where('id', $recordId)
                ->delete();

            return $response;
        }

        $responseBody = $response->getContent();

        if ($responseBody === false) {
            DB::table('integration_idempotency_keys')
                ->where('id', $recordId)
                ->delete();

            return $response;
        }

        DB::table('integration_idempotency_keys')
            ->where('id', $recordId)
            ->update([
                'status_code' => $response->getStatusCode(),
                'content_type' => $response->headers->get('Content-Type'),
                'response_body' => $responseBody,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

        return $response;
    }
}
