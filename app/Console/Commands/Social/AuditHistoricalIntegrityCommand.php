<?php

namespace App\Console\Commands\Social;

use App\Domain\Social\Services\CanonicalJsonEncoder;
use App\Enums\Social\PublicationStatus;
use App\Models\ClientReviewToken;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostPublication;
use App\Support\Monitoring\TracksSystemCommandRuns;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditHistoricalIntegrityCommand extends Command
{
    use TracksSystemCommandRuns;

    protected $signature = 'social:audit-historical-integrity
        {--post-id=* : Limita l’audit a uno o più post}
        {--publication-id=* : Limita l’audit a una o più publication}
        {--chunk=200 : Numero di publication elaborate per blocco}';

    protected $description = 'Verifica snapshot, catene di retry, versioni, pivot e token storici senza modificare dati';

    public function handle(CanonicalJsonEncoder $encoder): int
    {
        $postIds = $this->positiveIds((array) $this->option('post-id'));
        $publicationIds = $this->positiveIds((array) $this->option('publication-id'));
        $chunkSize = max(1, min(1000, (int) $this->option('chunk')));

        return $this->runTracked(
            $this->getName(),
            function () use (
                $encoder,
                $postIds,
                $publicationIds,
                $chunkSize
            ): int {
                $violations = [];
                $structuralPostIds = $this->structuralPostScope(
                    $postIds,
                    $publicationIds
                );

                $this->auditPublications(
                    $encoder,
                    $postIds,
                    $publicationIds,
                    $chunkSize,
                    $violations
                );
                if ($structuralPostIds !== []) {
                    $scopedPostIds = $structuralPostIds ?? [];
                    $this->auditVersionOwnership(
                        $scopedPostIds,
                        $violations
                    );
                    $this->auditMediaPivotOwnership(
                        $scopedPostIds,
                        $violations
                    );
                    $this->auditReviewTokens(
                        $scopedPostIds,
                        $violations
                    );
                }

                if ($violations === []) {
                    $this->info('Audit storico completato: nessuna violazione rilevata.');

                    return self::SUCCESS;
                }

                $this->table(
                    ['Ambito', 'ID', 'Codice', 'Dettaglio'],
                    array_map(
                        fn (array $violation): array => [
                            $violation['scope'],
                            $violation['id'],
                            $violation['code'],
                            $violation['detail'],
                        ],
                        $violations
                    )
                );
                $this->error(
                    'Audit storico fallito: '.count($violations).' violazioni.'
                );

                return self::FAILURE;
            },
            [
                'post_ids' => $postIds,
                'publication_ids' => $publicationIds,
            ]
        );
    }

    /**
     * Null means unscoped; an empty array means that an explicit scope matched
     * no publication and therefore no structural audit should run.
     *
     * @param  list<int>  $postIds
     * @param  list<int>  $publicationIds
     * @return list<int>|null
     */
    private function structuralPostScope(
        array $postIds,
        array $publicationIds
    ): ?array {
        if ($postIds === [] && $publicationIds === []) {
            return null;
        }

        if ($publicationIds === []) {
            return $postIds;
        }

        $query = MarketingCampaignPostPublication::query()
            ->whereIn('id', $publicationIds);

        if ($postIds !== []) {
            $query->whereIn('marketing_campaign_post_id', $postIds);
        }

        return $query
            ->pluck('marketing_campaign_post_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $postIds
     * @param  list<int>  $publicationIds
     * @param  array<int, array{scope: string, id: int|string, code: string, detail: string}>  $violations
     */
    private function auditPublications(
        CanonicalJsonEncoder $encoder,
        array $postIds,
        array $publicationIds,
        int $chunkSize,
        array &$violations
    ): void {
        $query = MarketingCampaignPostPublication::query()
            ->with(['version', 'retryOf'])
            ->orderBy('id');

        if ($postIds !== []) {
            $query->whereIn('marketing_campaign_post_id', $postIds);
        }
        if ($publicationIds !== []) {
            $query->whereIn('id', $publicationIds);
        }

        $query->chunkById($chunkSize, function ($publications) use (
            $encoder,
            &$violations
        ): void {
            foreach ($publications as $publication) {
                $id = $publication->id;

                if ($publication->snapshot_schema_version === null) {
                    if ($publication->status !== PublicationStatus::Abandoned) {
                        $this->violate(
                            $violations,
                            'publication',
                            $id,
                            'legacy_not_abandoned',
                            'Publication priva di snapshot ma ancora operativa.'
                        );
                    }

                    continue;
                }

                if (
                    ! $publication->version
                    || $publication->version->marketing_campaign_post_id !==
                        $publication->marketing_campaign_post_id
                ) {
                    $this->violate(
                        $violations,
                        'publication',
                        $id,
                        'foreign_or_missing_version',
                        'La versione non esiste o appartiene a un altro post.'
                    );
                }

                $snapshot = $publication->payload_snapshot;
                if (! is_array($snapshot)) {
                    $this->violate(
                        $violations,
                        'publication',
                        $id,
                        'missing_snapshot',
                        'Payload snapshot assente o non valido.'
                    );

                    continue;
                }

                $computedHash = hash('sha256', $encoder->encode($snapshot));
                if (
                    ! is_string($publication->snapshot_hash)
                    || ! hash_equals($publication->snapshot_hash, $computedHash)
                ) {
                    $this->violate(
                        $violations,
                        'publication',
                        $id,
                        'snapshot_hash_mismatch',
                        'Il payload non corrisponde allo snapshot_hash persistito.'
                    );
                }

                foreach ([
                    'post_id' => $publication->marketing_campaign_post_id,
                    'version_id' => $publication->marketing_campaign_post_version_id,
                    'platform' => $publication->platform->value,
                    'schema_version' => $publication->snapshot_schema_version,
                ] as $field => $expected) {
                    if (($snapshot[$field] ?? null) !== $expected) {
                        $this->violate(
                            $violations,
                            'publication',
                            $id,
                            "snapshot_{$field}_mismatch",
                            "Il campo snapshot {$field} non coincide con il record."
                        );
                    }
                }

                $snapshotTarget = $snapshot['target'] ?? null;
                if (! is_array($snapshotTarget)) {
                    $this->violate(
                        $violations,
                        'publication',
                        $id,
                        'invalid_snapshot_target',
                        'Il target dello snapshot non Ã¨ un oggetto valido.'
                    );
                }

                $snapshotAccountId = is_array($snapshotTarget)
                    ? ($snapshotTarget['social_account_id'] ?? null)
                    : null;
                if ($snapshotAccountId !== $publication->client_social_account_id) {
                    $this->violate(
                        $violations,
                        'publication',
                        $id,
                        'snapshot_account_mismatch',
                        'Il target dello snapshot non coincide con l’account persistito.'
                    );
                }

                $expectedIdempotencyKey = hash('sha256', implode('|', [
                    $publication->snapshot_schema_version,
                    $publication->marketing_campaign_post_version_id,
                    $publication->platform->value,
                    $publication->client_social_account_id,
                    $publication->snapshot_hash,
                ]));
                if (
                    ! is_string($publication->idempotency_key)
                    || ! hash_equals(
                        $publication->idempotency_key,
                        $expectedIdempotencyKey
                    )
                ) {
                    $this->violate(
                        $violations,
                        'publication',
                        $id,
                        'idempotency_key_mismatch',
                        'La chiave di idempotenza non deriva dai campi immutabili.'
                    );
                }

                $this->auditSnapshotMedia($publication, $snapshot, $violations);
                $this->auditRetryChain($publication, $violations);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<int, array{scope: string, id: int|string, code: string, detail: string}>  $violations
     */
    private function auditSnapshotMedia(
        MarketingCampaignPostPublication $publication,
        array $snapshot,
        array &$violations
    ): void {
        if (! $publication->version) {
            return;
        }

        $rawMedia = $snapshot['media'] ?? null;
        if (! is_array($rawMedia) || ! array_is_list($rawMedia)) {
            $this->violate(
                $violations,
                'publication',
                $publication->id,
                'invalid_snapshot_media',
                'Il campo media dello snapshot non Ã¨ una lista valida.'
            );

            return;
        }

        $normalizedMedia = [];
        $mediaIds = [];
        $sortOrders = [];
        foreach ($rawMedia as $index => $media) {
            $mediaId = is_array($media) ? ($media['media_id'] ?? null) : null;
            $sortOrder = is_array($media) ? ($media['sort_order'] ?? null) : null;

            if (
                ! is_int($mediaId)
                || $mediaId <= 0
                || ! is_int($sortOrder)
                || $sortOrder < 0
                || in_array($mediaId, $mediaIds, true)
                || in_array($sortOrder, $sortOrders, true)
            ) {
                $this->violate(
                    $violations,
                    'publication',
                    $publication->id,
                    'invalid_snapshot_media',
                    "Il descrittore media all'indice {$index} non Ã¨ valido."
                );

                return;
            }

            $mediaIds[] = $mediaId;
            $sortOrders[] = $sortOrder;
            $normalizedMedia[] = [
                'media_id' => $mediaId,
                'sort_order' => $sortOrder,
            ];
        }

        $snapshotMedia = collect($normalizedMedia)
            ->sortBy('sort_order')
            ->pluck('media_id')
            ->values()
            ->all();
        $pivotMedia = $publication->version->mediaItems()
            ->pluck('marketing_campaign_post_media.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        if ($snapshotMedia !== $pivotMedia) {
            $this->violate(
                $violations,
                'publication',
                $publication->id,
                'snapshot_media_mismatch',
                'Ordine o identità dei media differiscono dalla pivot congelata.'
            );
        }
    }

    /**
     * @param  array<int, array{scope: string, id: int|string, code: string, detail: string}>  $violations
     */
    private function auditRetryChain(
        MarketingCampaignPostPublication $publication,
        array &$violations
    ): void {
        if ($publication->retry_of_publication_id === null) {
            if ($publication->attempt_count !== 1) {
                $this->violate(
                    $violations,
                    'publication',
                    $publication->id,
                    'invalid_root_attempt',
                    'Una publication radice deve avere attempt_count 1.'
                );
            }

            return;
        }

        $root = $publication->retryOf;
        if (
            ! $root
            || $root->retry_of_publication_id !== null
            || $root->attempt_count !== 1
        ) {
            $this->violate(
                $violations,
                'publication',
                $publication->id,
                'invalid_retry_root',
                'La radice della catena di retry è assente o non valida.'
            );

            return;
        }

        if ($publication->attempt_count <= 1) {
            $this->violate(
                $violations,
                'publication',
                $publication->id,
                'invalid_retry_attempt',
                'Un retry deve avere attempt_count maggiore di 1.'
            );
        }

        foreach ([
            'marketing_campaign_post_id',
            'marketing_campaign_post_version_id',
            'client_social_account_id',
            'platform',
            'snapshot_schema_version',
            'snapshot_hash',
            'idempotency_key',
            'payload_snapshot',
        ] as $field) {
            $childValue = $publication->getAttribute($field);
            $rootValue = $root->getAttribute($field);

            if ($childValue != $rootValue) {
                $this->violate(
                    $violations,
                    'publication',
                    $publication->id,
                    'retry_identity_mismatch',
                    "Il campo {$field} diverge dalla publication radice."
                );
            }
        }
    }

    /**
     * @param  list<int>  $postIds
     * @param  array<int, array{scope: string, id: int|string, code: string, detail: string}>  $violations
     */
    private function auditVersionOwnership(array $postIds, array &$violations): void
    {
        $query = DB::table('marketing_campaign_posts as posts')
            ->join(
                'marketing_campaign_post_versions as versions',
                'versions.id',
                '=',
                'posts.current_version_id'
            )
            ->whereColumn(
                'versions.marketing_campaign_post_id',
                '!=',
                'posts.id'
            )
            ->select(['posts.id', 'posts.current_version_id']);

        if ($postIds !== []) {
            $query->whereIn('posts.id', $postIds);
        }

        foreach ($query->get() as $row) {
            $this->violate(
                $violations,
                'post',
                $row->id,
                'foreign_current_version',
                "La versione corrente {$row->current_version_id} appartiene a un altro post."
            );
        }
    }

    /**
     * @param  list<int>  $postIds
     * @param  array<int, array{scope: string, id: int|string, code: string, detail: string}>  $violations
     */
    private function auditMediaPivotOwnership(array $postIds, array &$violations): void
    {
        $query = DB::table('marketing_campaign_post_version_media as pivot')
            ->join(
                'marketing_campaign_post_versions as versions',
                'versions.id',
                '=',
                'pivot.marketing_campaign_post_version_id'
            )
            ->join(
                'marketing_campaign_post_media as media',
                'media.id',
                '=',
                'pivot.marketing_campaign_post_media_id'
            )
            ->whereColumn(
                'versions.marketing_campaign_post_id',
                '!=',
                'media.marketing_campaign_post_id'
            )
            ->select([
                'pivot.marketing_campaign_post_version_id as version_id',
                'pivot.marketing_campaign_post_media_id as media_id',
                'versions.marketing_campaign_post_id as post_id',
            ]);

        if ($postIds !== []) {
            $query->whereIn('versions.marketing_campaign_post_id', $postIds);
        }

        foreach ($query->get() as $row) {
            $this->violate(
                $violations,
                'version',
                $row->version_id,
                'foreign_media_pivot',
                "Il media {$row->media_id} appartiene a un altro post."
            );
        }
    }

    /**
     * @param  list<int>  $postIds
     * @param  array<int, array{scope: string, id: int|string, code: string, detail: string}>  $violations
     */
    private function auditReviewTokens(array $postIds, array &$violations): void
    {
        $query = ClientReviewToken::query()
            ->where('reviewable_type', MarketingCampaignPost::class)
            ->where(function ($builder): void {
                $builder->whereNull('used_at')
                    ->where(function ($expiry): void {
                        $expiry->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    });
            });

        if ($postIds !== []) {
            $query->whereIn('reviewable_id', $postIds);
        }

        foreach ($query->get() as $token) {
            $version = $token->marketing_campaign_post_version_id
                ? DB::table('marketing_campaign_post_versions')
                    ->where('id', $token->marketing_campaign_post_version_id)
                    ->first()
                : null;

            if (! $version || (int) $version->marketing_campaign_post_id !== $token->reviewable_id) {
                $this->violate(
                    $violations,
                    'review_token',
                    $token->id,
                    'unfrozen_or_foreign_version',
                    'Un token attivo deve congelare una versione dello stesso post.'
                );
            }
        }
    }

    /**
     * @param  array<int, array{scope: string, id: int|string, code: string, detail: string}>  $violations
     */
    private function violate(
        array &$violations,
        string $scope,
        int|string $id,
        string $code,
        string $detail
    ): void {
        $violations[] = compact('scope', 'id', 'code', 'detail');
    }

    /**
     * @param  array<mixed>  $values
     * @return list<int>
     */
    private function positiveIds(array $values): array
    {
        return collect($values)
            ->filter(fn (mixed $value): bool => ctype_digit((string) $value) && (int) $value > 0)
            ->map(fn (mixed $value): int => (int) $value)
            ->unique()
            ->values()
            ->all();
    }
}
