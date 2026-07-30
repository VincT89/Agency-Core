<?php

namespace App\Domain\Social\Actions;

use App\Domain\Social\Services\CanonicalJsonEncoder;
use App\Domain\Social\Services\MarketingCampaignPostPublicationSnapshotBuilder;
use App\Domain\Social\Services\MediaIntegrityMetadataReader;
use App\Enums\Social\PublicationStatus;
use App\Enums\Social\SocialPlatform;
use App\Exceptions\Social\NextcloudFileNotFoundException;
use App\Exceptions\Social\NextcloudPermanentFailureException;
use App\Exceptions\Social\NextcloudTemporaryUnavailableException;
use App\Models\AgencySocialAsset;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostPublication;
use App\Models\MarketingCampaignPostVersion;
use App\Services\Integrations\Nextcloud\NextcloudService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateMarketingCampaignPostPublicationAction
{
    public function __construct(
        private MarketingCampaignPostPublicationSnapshotBuilder $snapshotBuilder,
        private CanonicalJsonEncoder $encoder,
        private NextcloudService $nextcloudService,
        private ResolveFrozenPublicationTargetAction $resolveTargetAction
    ) {}

    public function execute(
        MarketingCampaignPost $post,
        MarketingCampaignPostVersion $version,
        SocialPlatform $platform,
        ClientSocialAccount $account,
        array $privacyOptions = [],
        string $publicationType = 'publish',
        array $platformOptions = []
    ): MarketingCampaignPostPublication {

        // Logical check before lock
        if ($version->marketing_campaign_post_id !== $post->id) {
            throw new \Exception('La versione non appartiene a questo post.');
        }

        // We could build a tentative logical key to lock at application level for concurrency
        // But since snapshotHash depends on media size, we can just use a simple lock by version and account
        $lockKey = "create_pub_{$version->id}_{$account->id}_{$platform->value}";
        $lock = Cache::lock($lockKey, 30);

        if (! $lock->get()) {
            throw new \Exception('Creazione publication già in corso per questa versione e account.');
        }

        try {
            // 1. Gather Media metadata BEFORE the transaction to avoid long DB locks on network/storage IO
            $mediaItems = $version->mediaItems()->get();
            $mediaMetadataCache = [];
            $initialMediaPivotState = [];

            foreach ($mediaItems as $media) {
                $initialMediaPivotState[] = [
                    'media_id' => $media->id,
                    'sort_order' => $media->pivot->sort_order,
                ];

                $metadata = [
                    'size_bytes' => 0,
                    'sha256' => null,
                    'etag' => null,
                ];

                $storageSource = in_array($media->source, ['nextcloud']) ? 'nextcloud' : 'local';

                if ($storageSource === 'local' && $media->path) {
                    try {
                        $integrity = app(
                            MediaIntegrityMetadataReader::class
                        )->readLocal($media->disk ?: 'public', $media->path);
                        $metadata['size_bytes'] = $integrity['source_size_bytes'];
                        $metadata['sha256'] = $integrity['sha256'];

                        if (
                            $media->source_size_bytes !== null
                            && $media->source_size_bytes !== $integrity['source_size_bytes']
                        ) {
                            throw new \Exception(
                                "Dimensione del media locale {$media->id} cambiata dopo l'acquisizione."
                            );
                        }
                        if (
                            filled($media->sha256)
                            && ! hash_equals($media->sha256, $integrity['sha256'])
                        ) {
                            throw new \Exception(
                                "Contenuto del media locale {$media->id} cambiato dopo l'acquisizione."
                            );
                        }
                        if (
                            filled($media->mime_type)
                            && $media->mime_type !== $integrity['mime_type']
                        ) {
                            throw new \Exception(
                                "MIME type del media locale {$media->id} cambiato dopo l'acquisizione."
                            );
                        }
                    } catch (\Exception $e) {
                        throw new \Exception(
                            "Impossibile verificare l'integrità del media locale {$media->id}: {$e->getMessage()}",
                            0,
                            $e
                        );
                    }
                } elseif ($storageSource === 'nextcloud' && $media->nextcloud_path) {
                    try {
                        $propfind = $this->nextcloudService->getFileInfo($media->nextcloud_path);
                        $metadata['size_bytes'] = $propfind->sizeBytes;
                        $metadata['etag'] = $propfind->etag;

                        if (
                            $media->source_size_bytes !== null
                            && $media->source_size_bytes !== $propfind->sizeBytes
                        ) {
                            throw new \Exception("Dimensione file Nextcloud cambiata per il media {$media->id}. Prevista: {$media->source_size_bytes}, Trovata: {$propfind->sizeBytes}");
                        }
                        if ($media->nextcloud_file_id && $media->nextcloud_file_id !== $propfind->fileId) {
                            throw new \Exception("File ID Nextcloud cambiato per il media {$media->id}.");
                        }
                        if ($media->nextcloud_etag && $media->nextcloud_etag !== $propfind->etag) {
                            throw new \Exception("ETag Nextcloud cambiato per il media {$media->id}.");
                        }
                        if ($media->mime_type && $media->mime_type !== $propfind->mimeType) {
                            throw new \Exception("MIME type Nextcloud cambiato per il media {$media->id}.");
                        }
                    } catch (NextcloudFileNotFoundException $e) {
                        throw new \Exception("File Nextcloud rimosso o inaccessibile prima della pubblicazione: {$e->getMessage()}");
                    } catch (NextcloudTemporaryUnavailableException $e) {
                        throw new \Exception("Servizio Nextcloud temporaneamente non disponibile: {$e->getMessage()}");
                    } catch (NextcloudPermanentFailureException $e) {
                        throw new \Exception("Configurazione o risposta Nextcloud non valida: {$e->getMessage()}");
                    } catch (\Exception $e) {
                        throw new \Exception("Impossibile verificare metadati Nextcloud: {$e->getMessage()}");
                    }
                }
                $mediaMetadataCache[$media->id] = $metadata;
            }

            // 2. Short DB Transaction
            return DB::transaction(function () use ($post, $version, $platform, $account, $privacyOptions, $publicationType, $mediaMetadataCache, $initialMediaPivotState, $platformOptions) {

                // Lock records explicitly
                $post = MarketingCampaignPost::where('id', $post->id)->lockForUpdate()->firstOrFail();
                $version = $post->versions()->where('id', $version->id)->lockForUpdate()->firstOrFail();
                $account = ClientSocialAccount::where('id', $account->id)->lockForUpdate()->firstOrFail();
                $lockedAsset = null;
                if ($account->agency_social_asset_id) {
                    $lockedAsset = AgencySocialAsset::where('id', $account->agency_social_asset_id)
                        ->lockForUpdate()
                        ->firstOrFail();
                }

                // Validate relations and authorizations
                if ($version->marketing_campaign_post_id !== $post->id) {
                    throw new \Exception('La versione non appartiene a questo post.');
                }
                if ($account->client_id !== $post->campaign->client_id) {
                    throw new \Exception("L'account social non appartiene al cliente di questa campagna.");
                }
                if ($account->platform !== $platform) {
                    throw new \Exception("L'account social specificato non corrisponde alla piattaforma richiesta.");
                }

                // Verify that this version is authorized (e.g. currentVersion or specifically approved)
                if ($post->current_version_id !== $version->id) {
                    // Check if it's approved anyway, or strictly require it to be current
                    throw new \Exception('La versione richiesta non e la versione corrente attiva/approvata del post.');
                }

                // Re-read media pivot to ensure no race condition modified the media attached to this version
                $currentMediaItems = $version->mediaItems()->get();
                $currentMediaPivotState = [];
                foreach ($currentMediaItems as $media) {
                    $currentMediaPivotState[] = [
                        'media_id' => $media->id,
                        'sort_order' => $media->pivot->sort_order,
                    ];
                }

                if ($initialMediaPivotState !== $currentMediaPivotState) {
                    throw new \Exception('La composizione dei media e cambiata durante la preparazione. Riprovare.');
                }

                // Resolve target INSIDE the transaction to guarantee consistency
                $account->setRelation('agencyAsset', $lockedAsset);
                $target = $this->resolveTargetAction->execute($platform, $account);

                // Ensure media metadata is passed to builder
                $snapshot = $this->snapshotBuilder->build(
                    version: $version,
                    platform: $platform,
                    target: $target,
                    privacyOptions: $privacyOptions,
                    publicationType: $publicationType,
                    mediaMetadataCache: $mediaMetadataCache,
                    platformOptions: $platformOptions
                );

                $schemaVersion = 1;

                // Compute Canonical Hash
                $canonicalJson = $this->encoder->encode($snapshot);
                $snapshotHash = hash('sha256', $canonicalJson);

                // Calculate Logical Idempotency Key
                $idempotencyKeyInput = implode('|', [
                    $schemaVersion,
                    $version->id,
                    $platform->value,
                    $account->id,
                    $snapshotHash,
                ]);
                $idempotencyKey = hash('sha256', $idempotencyKeyInput);

                // Explicit insert catching unique constraint violation for robust concurrency
                try {
                    $publication = MarketingCampaignPostPublication::create([
                        'idempotency_key' => $idempotencyKey,
                        'attempt_count' => 1,
                        'marketing_campaign_post_id' => $post->id,
                        'marketing_campaign_post_version_id' => $version->id,
                        'client_social_account_id' => $account->id,
                        'platform' => $platform->value,
                        'status' => PublicationStatus::Pending->value,
                        'payload_snapshot' => $snapshot->jsonSerialize(),
                        'snapshot_schema_version' => $schemaVersion,
                        'snapshot_hash' => $snapshotHash,
                        'publishing_started_at' => null,
                        'stale_deadline_at' => now()->addMinutes(
                            max(
                                1,
                                (int) config(
                                    'social.production_readiness.pending_stale_minutes',
                                    15
                                )
                            )
                        ),
                        'correlation_id' => Str::uuid()->toString(),
                        'poll_count' => 0,
                    ]);

                    return $publication;
                } catch (QueryException $e) {
                    // Check if it's a unique constraint violation on idempotency_key
                    if ($e->errorInfo[1] === 1062 || $e->errorInfo[1] === 19) { // 1062 MySQL, 19 SQLite
                        $existing = MarketingCampaignPostPublication::where('idempotency_key', $idempotencyKey)
                            ->where('attempt_count', 1)
                            ->first();

                        if ($existing) {
                            return $existing;
                        }
                    }
                    throw $e;
                }
            });
        } finally {
            $lock->release();
        }
    }
}
