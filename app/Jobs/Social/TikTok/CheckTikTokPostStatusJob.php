<?php

namespace App\Jobs\Social\TikTok;

use App\Domain\Social\Actions\SyncMarketingCampaignPostPublicationStatusAction;
use App\Domain\Social\DTOs\TikTokPostStatusResult;
use App\Domain\Social\TikTok\TikTokContentPostingService;
use App\Domain\Social\TikTok\TikTokPostStatusService;
use App\Enums\Social\PublicationFailureClassification;
use App\Enums\Social\PublicationStatus;
use App\Enums\Social\SocialApiStatus;
use App\Models\MarketingCampaignPostPublication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckTikTokPostStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 10;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public function __construct(
        private readonly int $publicationId
    ) {
        $this->onQueue('social-reconciliation');
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping((string) $this->publicationId))
                ->expireAfter(900),
        ];
    }

    public function backoff(): array
    {
        return [60, 120, 300, 600];
    }

    public function handle(
        TikTokContentPostingService $contentService,
        TikTokPostStatusService $statusService
    ): void {
        $publication = MarketingCampaignPostPublication::find($this->publicationId);
        if (! $publication || $publication->status !== PublicationStatus::Publishing) {
            return;
        }

        $publishId = $publication->external_task_id
            ?: $publication->external_container_id;

        if (! $publishId) {
            $this->finish(
                PublicationStatus::Failed,
                'Nessun publish_id di TikTok trovato per il polling.',
                PublicationFailureClassification::Permanent
            );

            return;
        }

        $account = $publication->socialAccount;
        if (! $account || ! $account->access_token) {
            $this->finish(
                PublicationStatus::NeedsManualReview,
                'Account non trovato o token mancante per il polling TikTok.',
                PublicationFailureClassification::ManualReview
            );

            return;
        }

        $this->incrementPollCount();

        try {
            $result = $contentService->getPostStatus(
                $account->access_token,
                (string) $publishId
            );
        } catch (\Throwable $e) {
            Log::warning('Errore temporaneo durante il polling TikTok', [
                'publication_id' => $this->publicationId,
                'error' => $e->getMessage(),
            ]);

            $response = [
                'status' => 'transport_error',
                'polled_at' => now()->toISOString(),
                'error_message' => $e->getMessage(),
            ];

            $this->retryOrEscalate($response, 'Errore di trasporto durante il polling TikTok.');

            return;
        }

        $formattedResponse = $this->formatResponse($result);

        if ($result->isAuthError) {
            $account->update([
                'api_status' => SocialApiStatus::TokenExpired,
                'last_api_error' => 'Token TikTok scaduto o permessi insufficienti.',
                'api_notes' => 'Ricollegare account TikTok.',
                'last_api_check_at' => now(),
            ]);

            $this->finish(
                PublicationStatus::NeedsManualReview,
                $result->errorMessage
                    ?? 'Token TikTok scaduto o permessi insufficienti.',
                PublicationFailureClassification::ManualReview,
                $formattedResponse
            );

            return;
        }

        if ($result->isTemporaryError) {
            $this->retryOrEscalate(
                $formattedResponse,
                $result->errorMessage ?? 'Errore temporaneo restituito da TikTok.'
            );

            return;
        }

        if ($result->isPermanentError) {
            $this->finish(
                PublicationStatus::Failed,
                $result->errorMessage ?? 'Errore API permanente restituito da TikTok.',
                PublicationFailureClassification::Permanent,
                $formattedResponse
            );

            return;
        }

        $mappedStatus = $statusService->mapStatus($result->status);

        if ($mappedStatus === PublicationStatus::Publishing) {
            $this->retryOrEscalate(
                $formattedResponse,
                "TikTok è ancora nello stato {$result->status}."
            );

            return;
        }

        if ($mappedStatus === PublicationStatus::Published) {
            $this->finish(
                PublicationStatus::Published,
                null,
                null,
                $formattedResponse,
                'delivered_to_tiktok'
            );

            return;
        }

        if ($mappedStatus === PublicationStatus::NeedsManualReview) {
            $this->finish(
                PublicationStatus::NeedsManualReview,
                'Il contenuto è stato inviato alla inbox TikTok e richiede azione del creator.',
                PublicationFailureClassification::ManualReview,
                $formattedResponse,
                'awaiting_creator_inbox_action'
            );

            return;
        }

        $this->finish(
            PublicationStatus::Failed,
            $result->failReason
                ? "TikTok ha rifiutato la pubblicazione: {$result->failReason}"
                : 'La pubblicazione è fallita asincronamente su TikTok.',
            PublicationFailureClassification::Permanent,
            $formattedResponse,
            'failed_on_tiktok'
        );
    }

    public function failed(\Throwable $exception): void
    {
        $updated = $this->finish(
            PublicationStatus::NeedsManualReview,
            'Fallimento definitivo dopo i retry massimi per il polling TikTok.',
            PublicationFailureClassification::ManualReview,
            [
                'status' => 'job_failed',
                'polled_at' => now()->toISOString(),
                'error_message' => $exception->getMessage(),
            ]
        );

        if ($updated) {
            Log::error('TikTok polling definitivamente fallito', [
                'publication_id' => $this->publicationId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function retryOrEscalate(array $response, string $message): void
    {
        if ($this->attempts() >= $this->tries) {
            $this->finish(
                PublicationStatus::NeedsManualReview,
                "Timeout polling TikTok: {$message}",
                PublicationFailureClassification::ManualReview,
                $response
            );

            return;
        }

        DB::transaction(function () use ($response) {
            $publication = MarketingCampaignPostPublication::whereKey($this->publicationId)
                ->lockForUpdate()
                ->first();

            if (! $publication || $publication->status !== PublicationStatus::Publishing) {
                return;
            }

            $publication->update([
                'provider_last_response' => $response,
                'provider_state_payload' => array_merge(
                    $publication->provider_state_payload ?? [],
                    ['last_poll_status' => PublicationStatus::Publishing->value]
                ),
                'failure_classification' => PublicationFailureClassification::Temporary->value,
            ]);
        });

        $this->release(min(max($this->attempts(), 1) * 60, 600));
    }

    private function finish(
        PublicationStatus $status,
        ?string $message,
        ?PublicationFailureClassification $classification,
        ?array $response = null,
        ?string $deliveryState = null
    ): bool {
        $updated = DB::transaction(function () use (
            $status,
            $message,
            $classification,
            $response,
            $deliveryState
        ) {
            $publication = MarketingCampaignPostPublication::whereKey($this->publicationId)
                ->lockForUpdate()
                ->first();

            if (! $publication || $publication->status !== PublicationStatus::Publishing) {
                return false;
            }

            $update = [
                'status' => $status->value,
                'error_message' => $message,
                'failure_classification' => $classification?->value,
                'delivery_state' => $deliveryState,
                'published_at' => $status === PublicationStatus::Published ? now() : null,
            ];

            if ($response !== null) {
                $update['provider_last_response'] = $response;
                $update['response_snapshot'] = $response;
                $update['provider_state_payload'] = array_merge(
                    $publication->provider_state_payload ?? [],
                    ['last_poll_status' => $status->value]
                );
            }

            $publication->update($update);

            return true;
        });

        if ($updated) {
            $this->syncPost();
        }

        return $updated;
    }

    private function incrementPollCount(): void
    {
        DB::transaction(function () {
            $publication = MarketingCampaignPostPublication::whereKey($this->publicationId)
                ->lockForUpdate()
                ->first();

            if ($publication?->status === PublicationStatus::Publishing) {
                $publication->increment('poll_count');
            }
        });
    }

    private function formatResponse(TikTokPostStatusResult $result): array
    {
        return [
            'status' => $result->status,
            'http_status' => $result->httpStatus,
            'request_id' => $result->requestId,
            'polled_at' => now()->toISOString(),
            'error_message' => $result->errorMessage,
            'fail_reason' => $result->failReason,
            'response_data' => $result->responseData,
        ];
    }

    private function syncPost(): void
    {
        $publication = MarketingCampaignPostPublication::find($this->publicationId);
        if ($publication?->post) {
            app(SyncMarketingCampaignPostPublicationStatusAction::class)
                ->execute($publication->post);
        }
    }
}
