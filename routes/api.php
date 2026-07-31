<?php

use App\Http\Controllers\Api\V1\Integrations\N8n\MarketingCampaignPostFailedController;
use App\Http\Controllers\Api\V1\Integrations\N8n\MarketingCampaignPostResultController;
use App\Http\Controllers\Api\V1\Integrations\N8n\MarketingCampaignPostVersionController;
use App\Http\Controllers\Api\V1\Integrations\N8n\N8nChatbotController;
use App\Http\Controllers\Api\V1\Integrations\N8n\N8nTicketController;
use App\Http\Controllers\Api\V1\Integrations\Aruba\ArubaCallbackController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/integrations/n8n')
    ->middleware(['n8n.auth', 'n8n.idempotency', 'throttle:120,1'])
    ->name('api.v1.integrations.n8n.')
    ->group(function () {
        Route::get('/health', function () {
            return response()->json([
                'ok' => true,
                'provider' => 'n8n',
                'status' => 'ready',
            ]);
        })->name('health');

        Route::post('/tickets', [N8nTicketController::class, 'store'])->name('tickets.store');

        Route::post('/marketing-campaign-posts/result', [MarketingCampaignPostResultController::class, 'store'])->name('marketing-campaign-posts.result');
        Route::post('/marketing-campaign-posts/{post}/versions', [MarketingCampaignPostVersionController::class, 'store'])->name('marketing-campaign-posts.versions.store');
        Route::post('/marketing-campaign-posts/{post}/failed', [MarketingCampaignPostFailedController::class, 'store'])->name('marketing-campaign-posts.failed');

        Route::post('/chatbot/client-message', [N8nChatbotController::class, 'store'])->name('chatbot.client-message');
        Route::post('/chatbot/outgoing-messages/{messageId}/status', [N8nChatbotController::class, 'updateOutgoingMessageStatus'])->name('chatbot.outgoing-messages.status');
    });

Route::prefix('v1/integrations/aruba')
    ->middleware(['aruba.callback.auth', 'throttle:aruba-callbacks'])
    ->name('api.v1.integrations.aruba.')
    ->group(function () {
        Route::post('/updateInvoiceStatus', [ArubaCallbackController::class, 'updateInvoiceStatus'])
            ->name('invoice-status.update');
        Route::post('/createNotification', [ArubaCallbackController::class, 'createNotification'])
            ->name('notifications.store');
    });
