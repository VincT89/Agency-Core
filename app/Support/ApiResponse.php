<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    // Restituisce una risposta JSON di successo
    protected function success(array $data = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => $data,
        ], $status);
    }

    // Restituisce una risposta JSON di errore
    protected function error(string $message, array $errors = [], int $status = 400): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}
