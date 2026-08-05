<?php

namespace App\Domain\Social\Actions;

use App\Domain\Social\DTOs\PublicationExecutionOutcome;
use App\Domain\Social\Publishing\MetaPublisher;
use App\Domain\Social\Publishing\PublishResult;
use App\Domain\Social\Publishing\TikTokPublisher;
use App\Domain\Social\Services\MarketingCampaignPostPublicationIntegrityVerifier;
use App\Domain\Social\Services\PublicationSnapshotPreflightService;
use App\Enums\Social\IntegritySeverity;
use App\Enums\Social\PublicationFailureClassification;
use App\Enums\Social\PublicationStatus;
use App\Enums\Social\SocialPlatform;
use App\Exceptions\Social\PermanentPublicationException;
use App\Exceptions\Social\TemporaryPublicationException;
use App\Jobs\Social\CheckInstagramContainerStatusJob;
use App\Jobs\Social\TikTok\CheckTikTokPostStatusJob;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaignPostPublication;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExecuteMarketingCampaignPostPublicationAction
{
    public function __construct(
        private MarketingCampaignPostPublicationIntegrityVerifier $verifier,
        private PublicationSnapshotPreflightService $preflightService,
        private SyncMarketingCampaignPostPublicationStatusAction $syncAction,
        private ResolveFrozenPublicationTargetAction $resolveTargetAction
    ) {}

    public function execute(int $publicationId): PublicationExecutionOutcome
    {
        $publication = DB::transaction(function () use ($publicationId) {
            $publication = MarketingCampaignPostPublication::whereKey($publicationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($publication->status !== PublicationStatus::Pending) {
                if ($publication->snapshot_schema_version === null) {
                    throw new \Exception(
                        'Impossibile eseguire una publication legacy priva di snapshot canonico.'
                    );
                }

                throw new \Exception(
                    "La publication {$publicationId} non è in stato Pending."
                );
            }

            $publication->update([
                'status' => PublicationStatus::Publishing->value,
                'publishing_started_at' => now(),
                'stale_deadline_at' => now()->addMinutes(
                    config(
                        "social.publication_stale_deadlines.{$publication->platform->value}",
                        15
                    )
                ),
            ]);

            return $publication;
        });

        $result = null;
        $account = null;

        try {
            $integrity = $this->verifier->verify($publication);
            if (! $integrity->passed) {
                $classification = $integrity->severity === IntegritySeverity::Temporary &&
                    $integrity->retryable
                    ? PublicationFailureClassification::Temporary
                    : PublicationFailureClassification::ManualReview;

                $result = PublishResult::failure(
                    'Integrità compromessa: '.implode(', ', $integrity->errors),
                    $classification
                );
            }

            if ($result === null) {
                $preflight = $this->preflightService->runPreflight(
                    $publication->payload_snapshot
                );

                if (! $preflight->isPass) {
                    $result = PublishResult::failure(
                        'Preflight fallito: '.implode(', ', $preflight->errors),
                        PublicationFailureClassification::Permanent
                    );
                }
            }

            if ($result === null) {
                $account = ClientSocialAccount::with('agencyAsset')
                    ->find($publication->client_social_account_id);

                if (! $account) {
                    $result = PublishResult::failure(
                        'Account social live non trovato.',
                        PublicationFailureClassification::Permanent
                    );
                }
            }

            if ($result === null && $account) {
                $snapshotTarget = $publication->payload_snapshot['target'];

                if ($account->platform !== $publication->platform) {
                    $result = PublishResult::failure(
                        'La piattaforma dell’account non corrisponde alla publication.',
                        PublicationFailureClassification::Permanent
                    );
                } else {
                    try {
                        $liveTarget = $this->resolveTargetAction->execute(
                            $publication->platform,
                            $account
                        );
                    } catch (\Throwable $e) {
                        $result = PublishResult::failure(
                            'Impossibile risolvere il target live: '.$e->getMessage(),
                            PublicationFailureClassification::Permanent
                        );
                    }

                    if ($result === null) {
                        foreach (
                            ['social_account_id', 'external_id', 'page_id', 'profile_id'] as $field
                        ) {
                            if (($snapshotTarget[$field] ?? null) !== $liveTarget[$field]) {
                                $result = PublishResult::failure(
                                    "Il target live {$field} è cambiato rispetto allo snapshot.",
                                    PublicationFailureClassification::Permanent
                                );
                                break;
                            }
                        }
                    }
                }
            }

            if ($result === null && $account) {
                $publisher = match ($publication->platform) {
                    SocialPlatform::Facebook, SocialPlatform::Instagram => app(MetaPublisher::class),
                    SocialPlatform::Tiktok => app(TikTokPublisher::class),
                };

                $result = $publisher->publish(
                    $publication,
                    $account,
                    $publication->correlation_id
                );
            }
        } catch (PermanentPublicationException $e) {
            $result = PublishResult::failure(
                $e->getMessage(),
                PublicationFailureClassification::Permanent
            );
        } catch (TemporaryPublicationException $e) {
            $result = PublishResult::failure(
                $e->getMessage(),
                PublicationFailureClassification::Temporary
            );
        } catch (\Throwable $e) {
            Log::error('Errore inatteso durante la pubblicazione social', [
                'publication_id' => $publication->id,
                'error' => $e->getMessage(),
            ]);

            $result = PublishResult::failure(
                'Errore inatteso con esito potenzialmente ambiguo: '.$e->getMessage(),
                PublicationFailureClassification::ManualReview
            );
        }

        $simulationSnapshot = $result->responseSnapshot ?? [];
        $isSimulation = ($simulationSnapshot['dry_run'] ?? false) === true
            || ($simulationSnapshot['should_not_count_as_real_publication'] ?? false) === true;
        $simulationMessage = 'Simulazione completata: nessun contenuto è stato inviato al social. Disattiva SOCIAL_PUBLISHING_DRY_RUN e riprova.';

        if ($isSimulation && filled($result->externalPostId)) {
            $simulationSnapshot['simulation_reference'] = $result->externalPostId;
        }

        $transition = DB::transaction(function () use (
            $publication,
            $result,
            $isSimulation,
            $simulationSnapshot,
            $simulationMessage
        ) {
            $locked = MarketingCampaignPostPublication::whereKey($publication->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== PublicationStatus::Publishing) {
                return ['applied' => false, 'poll_platform' => null];
            }

            if ($isSimulation) {
                $locked->update([
                    'status' => PublicationStatus::NeedsManualReview->value,
                    'external_post_id' => null,
                    'external_container_id' => null,
                    'external_task_id' => null,
                    'external_permalink' => null,
                    'published_at' => null,
                    'error_message' => $simulationMessage,
                    'failure_classification' => PublicationFailureClassification::ManualReview->value,
                    'response_snapshot' => $simulationSnapshot,
                    'provider_last_response' => $simulationSnapshot,
                ]);

                return ['applied' => true, 'poll_platform' => null];
            }

            if ($result->success) {
                $update = [
                    'external_post_id' => $result->externalPostId,
                    'external_container_id' => $result->externalContainerId,
                    'external_task_id' => $result->externalTaskId,
                    'external_permalink' => $result->externalPermalink,
                    'response_snapshot' => $result->responseSnapshot,
                    'provider_last_response' => $result->responseSnapshot,
                    'error_message' => null,
                    'failure_classification' => null,
                ];

                if ($result->providerStatePayload !== null) {
                    $update['provider_state_payload'] = $result->providerStatePayload;
                }

                if ($result->isProcessing()) {
                    $update['status'] = PublicationStatus::Publishing->value;

                    if ($locked->platform === SocialPlatform::Instagram) {
                        $update['meta_processing_state'] = 'IN_PROGRESS';
                    }

                    $locked->update($update);

                    return [
                        'applied' => true,
                        'poll_platform' => $locked->platform,
                    ];
                }

                $update['status'] = PublicationStatus::Published->value;
                $update['published_at'] = now();
                $locked->update($update);

                return ['applied' => true, 'poll_platform' => null];
            }

            $locked->update([
                'status' => $result->failureClassification ===
                    PublicationFailureClassification::ManualReview
                    ? PublicationStatus::NeedsManualReview->value
                    : PublicationStatus::Failed->value,
                'error_message' => $result->errorMessage,
                'failure_classification' => $result->failureClassification?->value,
                'response_snapshot' => $result->responseSnapshot,
                'provider_last_response' => $result->responseSnapshot,
            ]);

            return ['applied' => true, 'poll_platform' => null];
        });

        if (! $transition['applied']) {
            $current = MarketingCampaignPostPublication::find($publication->id);

            return $current?->status === PublicationStatus::Published
                ? PublicationExecutionOutcome::success($current->response_snapshot)
                : PublicationExecutionOutcome::failure(
                    'Lo stato della publication è cambiato durante l’esecuzione.',
                    PublicationFailureClassification::ManualReview,
                    $current?->response_snapshot
                );
        }

        if ($transition['poll_platform'] === SocialPlatform::Instagram) {
            CheckInstagramContainerStatusJob::dispatch($publication->id)
                ->delay(now()->addSeconds(15))
                ->afterCommit();
        } elseif ($transition['poll_platform'] === SocialPlatform::Tiktok) {
            CheckTikTokPostStatusJob::dispatch($publication->id)
                ->delay(now()->addSeconds(60))
                ->afterCommit();
        }

        $current = MarketingCampaignPostPublication::find($publication->id);
        if ($current?->post) {
            $this->syncAction->execute($current->post);
        }

        if ($isSimulation) {
            return PublicationExecutionOutcome::failure(
                $simulationMessage,
                PublicationFailureClassification::ManualReview,
                $simulationSnapshot
            );
        }

        return $result->success
            ? PublicationExecutionOutcome::success($result->responseSnapshot)
            : PublicationExecutionOutcome::failure(
                $result->errorMessage,
                $result->failureClassification
                    ?? PublicationFailureClassification::Temporary,
                $result->responseSnapshot
            );
    }
}
