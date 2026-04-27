<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/integrations/n8n')
    ->middleware('n8n.auth')
    ->name('api.v1.integrations.n8n.')
    ->group(function () {
        Route::get('/health', function () {
            return response()->json([
                'ok' => true,
                'provider' => 'n8n',
                'status' => 'ready',
            ]);
        })->name('health');

        Route::post('/social/posts', [\App\Http\Controllers\Api\V1\Integrations\N8n\SocialPostController::class, 'store'])->name('social.posts.store');
        Route::post('/social/posts/{post}/versions', [\App\Http\Controllers\Api\V1\Integrations\N8n\SocialPostVersionController::class, 'store'])->name('social.posts.versions.store');
    });
