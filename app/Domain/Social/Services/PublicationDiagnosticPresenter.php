<?php

namespace App\Domain\Social\Services;

use App\Enums\Social\PublicationStatus;
use App\Enums\Social\SocialPlatform;
use App\Models\MarketingCampaignPostPublication;
use App\Support\Http\ProviderErrorSanitizer;
use Illuminate\Support\Str;

final class PublicationDiagnosticPresenter
{
    /**
     * @return array{
     *     message: ?string,
     *     initialization_accepted: ?bool,
     *     provider_status: ?string,
     *     provider_status_label: ?string,
     *     provider_code: ?string,
     *     request_reference: ?string,
     *     http_status: ?int
     * }
     */
    public function present(MarketingCampaignPostPublication $publication): array
    {
        $providerResponse = is_array($publication->provider_last_response)
            ? $publication->provider_last_response
            : [];
        $responseSnapshot = is_array($publication->response_snapshot)
            ? $publication->response_snapshot
            : [];
        $isTikTok = $publication->platform === SocialPlatform::Tiktok;

        $message = is_string($publication->error_message)
            ? $this->safeText($publication->error_message, 1000)
            : null;

        if (
            $message === null
            && in_array($publication->status, [
                PublicationStatus::Failed,
                PublicationStatus::NeedsManualReview,
            ], true)
        ) {
            $message = 'Il tentativo non si è concluso e non è stato registrato un motivo dettagliato.';
        }

        $providerStatus = $isTikTok
            ? $this->firstText([
                data_get($providerResponse, 'status'),
                data_get($providerResponse, 'response_data.data.status'),
                data_get($responseSnapshot, 'status'),
                data_get($responseSnapshot, 'response_data.data.status'),
            ], 80)
            : null;

        $providerCode = $isTikTok
            ? $this->firstText([
                data_get($providerResponse, 'response_data.error.code'),
                data_get($providerResponse, 'error.code'),
                data_get($responseSnapshot, 'response_data.error.code'),
                data_get($responseSnapshot, 'error.code'),
                data_get($responseSnapshot, 'provider_raw_response.response.error.code'),
            ], 120)
            : null;

        if (strtolower((string) $providerCode) === 'ok') {
            $providerCode = null;
        }

        $requestReference = $isTikTok
            ? $this->firstText([
                data_get($providerResponse, 'request_id'),
                data_get($providerResponse, 'response_data.error.log_id'),
                data_get($providerResponse, 'response_data.error.logid'),
                data_get($responseSnapshot, 'request_id'),
                data_get($responseSnapshot, 'response_data.error.log_id'),
                data_get($responseSnapshot, 'response_data.error.logid'),
                data_get($responseSnapshot, 'provider_raw_response.response.error.log_id'),
                data_get($responseSnapshot, 'provider_raw_response.response.error.logid'),
            ], 180)
            : null;

        $httpStatus = $isTikTok
            ? $this->firstHttpStatus([
                data_get($providerResponse, 'http_status'),
                data_get($responseSnapshot, 'http_status'),
            ])
            : null;

        return [
            'message' => $message,
            'initialization_accepted' => $isTikTok
                ? $this->initializationWasAccepted($publication, $responseSnapshot)
                : null,
            'provider_status' => $providerStatus,
            'provider_status_label' => $this->providerStatusLabel($providerStatus),
            'provider_code' => $providerCode,
            'request_reference' => $requestReference,
            'http_status' => $httpStatus,
        ];
    }

    private function initializationWasAccepted(
        MarketingCampaignPostPublication $publication,
        array $responseSnapshot
    ): bool {
        return filled($publication->external_task_id)
            || filled($publication->external_container_id)
            || filled(data_get($responseSnapshot, 'publish_task_id'))
            || filled(data_get($responseSnapshot, 'provider_raw_response.publish_id'));
    }

    private function providerStatusLabel(?string $status): ?string
    {
        if ($status === null) {
            return null;
        }

        return match (strtoupper($status)) {
            'PUBLISH_COMPLETE', 'PUBLISHED' => 'Pubblicato',
            'PROCESSING_UPLOAD' => 'Caricamento in elaborazione',
            'PROCESSING_DOWNLOAD' => 'Acquisizione del media in elaborazione',
            'SEND_TO_USER_INBOX' => 'Inviato alla inbox TikTok',
            'FAILED' => 'Rifiutato da TikTok',
            'HTTP_ERROR' => 'Errore HTTP durante il controllo',
            'API_ERROR' => 'Errore API durante il controllo',
            'TRANSPORT_ERROR' => 'Errore di collegamento durante il controllo',
            'JOB_FAILED' => 'Controllo automatico non completato',
            'UNKNOWN' => 'Stato non riconosciuto',
            default => 'Stato TikTok non riconosciuto',
        };
    }

    private function firstText(array $values, int $limit): ?string
    {
        foreach ($values as $value) {
            if (! is_scalar($value) || is_bool($value)) {
                continue;
            }

            $safeValue = $this->safeText((string) $value, $limit);
            if ($safeValue !== null) {
                return $safeValue;
            }
        }

        return null;
    }

    private function safeText(string $value, int $limit): ?string
    {
        $safeValue = ProviderErrorSanitizer::safeText($value);
        $safeValue = Str::limit(trim($safeValue), $limit, '');

        return $safeValue !== '' ? $safeValue : null;
    }

    private function firstHttpStatus(array $values): ?int
    {
        foreach ($values as $value) {
            if (! is_numeric($value)) {
                continue;
            }

            $status = (int) $value;
            if ($status >= 100 && $status <= 599) {
                return $status;
            }
        }

        return null;
    }
}
