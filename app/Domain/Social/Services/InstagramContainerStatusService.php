<?php

namespace App\Domain\Social\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstagramContainerStatusService
{
    /**
     * Controlla lo stato del container su Meta ed esegue il publish se pronto.
     * Ritorna un DTO puro, senza fare side-effects sul DB.
     */
    public function checkAndPublishContainer(string $containerId, string $accessToken, string $igAccountId, ?string $correlationId = null): InstagramContainerStatusResult
    {
        try {
            $client = Http::withHeaders([
                'X-Correlation-Id' => $correlationId ?? 'none'
            ]);

            $graphVersion = config('services.meta.graph_version', 'v19.0');
            $statusEndpoint = "https://graph.facebook.com/{$graphVersion}/{$containerId}";
            $statusResponse = $client->get($statusEndpoint, [
                'fields' => 'status_code,status',
                'access_token' => $accessToken,
            ]);

            if (!$statusResponse->successful()) {
                $errorData = $statusResponse->json();
                
                if ($statusResponse->clientError()) {
                    $errorCode = $errorData['error']['code'] ?? null;
                    // Codici temporanei: 4, 17, 32, 613 (rate limits/throttling)
                    $temporaryCodes = [4, 17, 32, 613];
                    
                    if (!in_array($errorCode, $temporaryCodes)) {
                        return new InstagramContainerStatusResult(
                            status: 'ERROR',
                            isPermanentError: true,
                            errorMessage: "Errore permanente (4xx) da Instagram nel recupero status Container: " . ($errorData['error']['message'] ?? 'Sconosciuto'),
                            responseData: $errorData
                        );
                    }
                }
                
                // Errore generico (o 5xx o rate limit). Ritorniamo error non permanente per far scattare retry.
                return new InstagramContainerStatusResult(
                    status: 'UNKNOWN',
                    isPermanentError: false,
                    errorMessage: 'Errore nel recupero status Container: ' . $statusResponse->body(),
                    responseData: $errorData
                );
            }

            $statusData = $statusResponse->json();
            $statusCode = $statusData['status_code'] ?? 'UNKNOWN';

            if ($statusCode === 'FINISHED') {
                // Procediamo con la pubblicazione finale del container
                $publishEndpoint = "https://graph.facebook.com/{$graphVersion}/{$igAccountId}/media_publish";
                $publishResponse = $client->post($publishEndpoint, [
                    'creation_id' => $containerId,
                    'access_token' => $accessToken,
                ]);

                if ($publishResponse->successful()) {
                    $publishData = $publishResponse->json();
                    return new InstagramContainerStatusResult(
                        status: 'FINISHED',
                        isPermanentError: false,
                        errorMessage: null,
                        responseData: $statusData,
                        externalPostId: $publishData['id'],
                        publishResponse: $publishData
                    );
                } else {
                    $publishErrorData = $publishResponse->json();
                    return new InstagramContainerStatusResult(
                        status: 'ERROR',
                        isPermanentError: true, // Se fallisce la media_publish consideriamolo errore da rivedere
                        errorMessage: "Errore nella media_publish di Instagram.",
                        responseData: $statusData, // Manteniamo lo status per referenza
                        externalPostId: null,
                        publishResponse: $publishErrorData
                    );
                }
            }

            // IN_PROGRESS, ERROR, o EXPIRED
            $isPermanent = in_array($statusCode, ['ERROR', 'EXPIRED']);
            return new InstagramContainerStatusResult(
                status: $statusCode,
                isPermanentError: $isPermanent,
                errorMessage: $isPermanent ? "Instagram ha riportato un errore nel container o è scaduto." : null,
                responseData: $statusData
            );

        } catch (\Exception $e) {
            Log::error('InstagramContainerStatusService Exception', [
                'error' => $e->getMessage(),
                'container_id' => $containerId,
            ]);
            
            return new InstagramContainerStatusResult(
                status: 'UNKNOWN',
                isPermanentError: false,
                errorMessage: 'Eccezione interna durante check/publish: ' . $e->getMessage(),
                responseData: null
            );
        }
    }
}
