<?php

namespace App\Domain\Social\Actions;

use App\Domain\Social\Services\MarketingCampaignPostPublicationIntegrityVerifier;
use App\Enums\Social\PublicationStatus;
use App\Models\MarketingCampaignPostPublication;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RetryMarketingCampaignPostPublicationAction
{
    public function __construct(
        private MarketingCampaignPostPublicationIntegrityVerifier $verifier
    ) {}

    public function execute(MarketingCampaignPostPublication $originalPublication): MarketingCampaignPostPublication
    {
        $verifiedOriginal = MarketingCampaignPostPublication::query()
            ->findOrFail($originalPublication->getKey());

        if ($verifiedOriginal->snapshot_schema_version === null) {
            throw new \Exception('Impossibile riprovare una publication legacy priva di snapshot canonico.');
        }

        $integrity = $this->verifier->verify($verifiedOriginal);
        if (! $integrity->passed) {
            throw new \Exception(
                'Impossibile riprovare: Snapshot non integro. Errori: '.implode(', ', $integrity->errors)
            );
        }

        $verifiedFingerprint = $this->immutableFingerprint($verifiedOriginal);

        try {
            return DB::transaction(function () use ($verifiedOriginal, $verifiedFingerprint) {
                $rootPublicationId = $verifiedOriginal->retry_of_publication_id ?? $verifiedOriginal->id;

                $lockedRoot = MarketingCampaignPostPublication::query()
                    ->whereKey($rootPublicationId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedOriginal = MarketingCampaignPostPublication::query()
                    ->whereKey($verifiedOriginal->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($this->immutableFingerprint($lockedOriginal) !== $verifiedFingerprint) {
                    throw new \Exception(
                        'Impossibile riprovare: la pubblicazione è cambiata dopo la verifica di integrità.'
                    );
                }

                if (! in_array(
                    $lockedOriginal->status,
                    [PublicationStatus::Failed, PublicationStatus::NeedsManualReview],
                    true
                )) {
                    throw new \Exception(
                        "Impossibile riprovare una pubblicazione nello stato {$lockedOriginal->status->value}. ".
                        'Consentito solo per Failed o NeedsManualReview.'
                    );
                }

                if ($lockedRoot->retry_of_publication_id !== null || $lockedRoot->attempt_count !== 1) {
                    throw new \Exception(
                        'Impossibile riprovare: la radice della catena di retry non è valida.'
                    );
                }

                foreach ($this->chainIdentity($lockedOriginal) as $field => $value) {
                    if ($this->chainIdentity($lockedRoot)[$field] !== $value) {
                        throw new \Exception(
                            "Impossibile riprovare: discrepanza del campo immutabile {$field} ".
                            'rispetto alla pubblicazione radice.'
                        );
                    }
                }

                if (
                    $lockedOriginal->id !== $lockedRoot->id &&
                    $lockedOriginal->retry_of_publication_id !== $lockedRoot->id
                ) {
                    throw new \Exception(
                        'Impossibile riprovare: la pubblicazione non appartiene alla catena dichiarata.'
                    );
                }

                $updatedRows = MarketingCampaignPostPublication::query()
                    ->whereKey($lockedOriginal->id)
                    ->whereIn('status', [
                        PublicationStatus::Failed->value,
                        PublicationStatus::NeedsManualReview->value,
                    ])
                    ->update([
                        'status' => PublicationStatus::Superseded->value,
                        'error_message' => 'Dismesso (sostituito da nuovo tentativo)',
                    ]);

                if ($updatedRows !== 1) {
                    throw new \Exception(
                        'Impossibile riprovare: la pubblicazione non è più nello stato atteso.'
                    );
                }

                $maxAttempt = (int) MarketingCampaignPostPublication::query()
                    ->where('idempotency_key', $lockedOriginal->idempotency_key)
                    ->max('attempt_count');

                return MarketingCampaignPostPublication::create([
                    'marketing_campaign_post_id' => $lockedOriginal->marketing_campaign_post_id,
                    'marketing_campaign_post_version_id' => $lockedOriginal->marketing_campaign_post_version_id,
                    'client_social_account_id' => $lockedOriginal->client_social_account_id,
                    'platform' => $lockedOriginal->platform->value,
                    'status' => PublicationStatus::Pending->value,
                    'correlation_id' => Str::uuid()->toString(),
                    'publishing_started_at' => null,
                    'stale_deadline_at' => null,
                    'attempt_count' => $maxAttempt + 1,
                    'poll_count' => 0,
                    'payload_snapshot' => $lockedOriginal->payload_snapshot,
                    'snapshot_schema_version' => $lockedOriginal->snapshot_schema_version,
                    'snapshot_hash' => $lockedOriginal->snapshot_hash,
                    'idempotency_key' => $lockedOriginal->idempotency_key,
                    'retry_of_publication_id' => $lockedRoot->id,
                ]);
            });
        } catch (QueryException $e) {
            $driverCode = (int) ($e->errorInfo[1] ?? 0);
            $sqlState = (string) ($e->errorInfo[0] ?? '');

            if ($driverCode === 1062 || $driverCode === 19 || $sqlState === '23000') {
                throw new \Exception(
                    'Impossibile riprovare: un altro tentativo è già in corso '.
                    'per questa pubblicazione (collisione chiave unica).',
                    0,
                    $e
                );
            }

            throw $e;
        }
    }

    private function immutableFingerprint(MarketingCampaignPostPublication $publication): array
    {
        return [
            ...$this->chainIdentity($publication),
            'attempt_count' => $publication->attempt_count,
            'retry_of_publication_id' => $publication->retry_of_publication_id,
            'correlation_id' => $publication->correlation_id,
        ];
    }

    private function chainIdentity(MarketingCampaignPostPublication $publication): array
    {
        return [
            'marketing_campaign_post_id' => $publication->marketing_campaign_post_id,
            'marketing_campaign_post_version_id' => $publication->marketing_campaign_post_version_id,
            'client_social_account_id' => $publication->client_social_account_id,
            'platform' => $publication->platform->value,
            'snapshot_schema_version' => $publication->snapshot_schema_version,
            'snapshot_hash' => $publication->snapshot_hash,
            'payload_snapshot' => $publication->payload_snapshot,
            'idempotency_key' => $publication->idempotency_key,
        ];
    }
}
