<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/integrations/n8n')
    ->middleware(['n8n.auth', 'throttle:120,1'])
    ->name('api.v1.integrations.n8n.')
    ->group(function () {
        Route::get('/health', function () {
            return response()->json([
                'ok' => true,
                'provider' => 'n8n',
                'status' => 'ready',
            ]);
        })->name('health');


        Route::post('/tickets', [\App\Http\Controllers\Api\V1\Integrations\N8n\N8nTicketController::class, 'store'])->name('tickets.store');

        Route::post('/marketing-campaign-posts/result', [\App\Http\Controllers\Api\V1\Integrations\N8n\MarketingCampaignPostResultController::class, 'store'])->name('marketing-campaign-posts.result');
        Route::post('/marketing-campaign-posts/{post}/versions', [\App\Http\Controllers\Api\V1\Integrations\N8n\MarketingCampaignPostVersionController::class, 'store'])->name('marketing-campaign-posts.versions.store');
    });
