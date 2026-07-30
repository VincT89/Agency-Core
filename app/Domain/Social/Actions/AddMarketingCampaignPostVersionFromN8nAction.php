<?php

namespace App\Domain\Social\Actions;

use App\Domain\Social\DTOs\AddMarketingCampaignPostVersionData;
use App\Domain\Social\DTOs\AddPostVersionResult;
use App\Domain\Social\Services\ImageStagerService;
use App\Domain\Social\Services\MarketingCampaignPostMediaUrlResolver;
use App\Domain\Social\Services\MarketingCampaignPostVersionMediaResolver;
use App\Domain\Social\Services\MediaIntegrityMetadataReader;
use App\Enums\Social\MarketingCampaignPostRegenerationType;
use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\Social\MarketingCampaignPostVersionSource;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostMedia;
use App\Models\MarketingCampaignPostVersion;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AddMarketingCampaignPostVersionFromN8nAction
{
    public function __construct(
        private readonly ImageStagerService $stager,
        private readonly MarketingCampaignPostVersionMediaResolver $resolver,
        private readonly MarketingCampaignPostMediaUrlResolver $urlResolver,
        private readonly ?EvaluateMarketingCampaignPostAutomationAction $automation = null
    ) {}

    public function execute(AddMarketingCampaignPostVersionData $data): AddPostVersionResult
    {
        // Ottimizzazione non transazionale per evitare il download di immagini già processate
        $earlyDuplicate = $this->fastCheckDuplicate($data);
        if ($earlyDuplicate) {
            return $earlyDuplicate;
        }

        $temporaryFiles = [];
        $promotedFiles = [];

        $committed = false;

        try {
            if ($data->regenerationType !== MarketingCampaignPostRegenerationType::Caption) {
                $temporaryFiles = $this->stager->downloadAndValidate($data->imageUrls);
            }

            $result = DB::transaction(function () use ($data, $temporaryFiles, &$promotedFiles) {
                $post = MarketingCampaignPost::query()
                    ->lockForUpdate()
                    ->findOrFail($data->postId);

                // callback riferita a richiesta esplicitamente cancellata
                if ($post->n8n_error === 'N8N_ERROR_FORCE_CANCELLED') {
                    return new AddPostVersionResult('ignored', null, 'request_cancelled');
                }

                // request ID non più atteso perché sostituito da una nuova richiesta (per lo stesso post) o compensato a null
                if ($data->requestId !== null && $post->n8n_request_id !== $data->requestId) {
                    return new AddPostVersionResult('conflict', null, 'request_id_outdated');
                }

                if (in_array($post->status, [
                    MarketingCampaignPostStatus::ClientApproved,
                    MarketingCampaignPostStatus::Approved,
                    MarketingCampaignPostStatus::Published,
                    MarketingCampaignPostStatus::Cancelled,
                ], true)) {
                    // post is in a final state. Could be an old callback arriving too late.
                    return new AddPostVersionResult('ignored', null, 'post_already_finalized');
                }

                // Se arriviamo qui, la callback è valida -> promuoviamo i file e creiamo la versione
                $promotedFiles = $this->stager->promote($temporaryFiles);

                $currentVersion = $post->currentVersion;
                $versionNumber = $post->versions()->max('version_number') + 1;

                $versionData = [
                    'marketing_campaign_post_id' => $post->id,
                    'created_by' => null, // AI created
                    'version_number' => $versionNumber,
                    'regeneration_type' => $data->regenerationType->value,
                    'source' => MarketingCampaignPostVersionSource::N8n->value,
                    'n8n_request_id' => $data->requestId,
                    'external_generation_id' => $data->externalGenerationId,
                    'prompt_used' => $data->promptUsed,
                    'raw_payload' => $data->rawPayload,
                ];

                if ($data->regenerationType === MarketingCampaignPostRegenerationType::Caption) {
                    $versionData['title'] = $data->title;
                    $versionData['caption'] = $data->caption;
                    $versionData['hashtags'] = $data->hashtags;
                } elseif ($data->regenerationType === MarketingCampaignPostRegenerationType::Image) {
                    $versionData['title'] = $currentVersion?->title;
                    $versionData['caption'] = $currentVersion?->caption;
                    $versionData['hashtags'] = $currentVersion?->hashtags;
                } else {
                    $versionData['title'] = $data->title;
                    $versionData['caption'] = $data->caption;
                    $versionData['hashtags'] = $data->hashtags;
                }

                // Placeholder for legacy fields, will update after pivot
                $versionData['image_url'] = null;
                $versionData['image_urls'] = [];
                $versionData['image_path'] = null;

                $version = MarketingCampaignPostVersion::create($versionData);

                // --- GESTIONE MEDIA E PIVOT ---
                if ($data->regenerationType === MarketingCampaignPostRegenerationType::Caption) {
                    $resolution = $this->resolver->resolveForPost($post);
                    $sourceMedia = $resolution->mediaItems;
                    $pivotData = [];
                    foreach ($sourceMedia as $index => $media) {
                        $pivotData[$media->id] = ['sort_order' => $index];
                    }
                    if (! empty($pivotData)) {
                        $version->mediaItems()->attach($pivotData);
                    }
                } else {
                    // Rigenerazione image o full: crea nuovi media e associazioni pivot.
                    $pivotData = [];
                    foreach ($promotedFiles as $index => $promotedPath) {
                        $integrity = app(
                            MediaIntegrityMetadataReader::class
                        )->readLocal('social_media', $promotedPath);
                        $mime = $integrity['mime_type'];

                        $media = MarketingCampaignPostMedia::create([
                            'marketing_campaign_post_id' => $post->id,
                            'path' => $promotedPath,
                            'disk' => 'social_media',
                            'source' => 'n8n',
                            'mime_type' => $mime,
                            'source_size_bytes' => $integrity['source_size_bytes'],
                            'sha256' => $integrity['sha256'],
                            'media_type' => MarketingCampaignPostMedia::detectMediaType($mime),
                            'sort_order' => $index, // Fallback order per il post
                        ]);

                        $pivotData[$media->id] = ['sort_order' => $index];
                    }
                    if (! empty($pivotData)) {
                        $version->mediaItems()->attach($pivotData);
                    }
                }

                $version->setRelation('post', $post);
                $finalResolution = $this->resolver->resolveForVersion($version);
                $finalResolvedUrls = $this->urlResolver->orderedDeliveryUrls($finalResolution->mediaItems);

                $primaryMedia = $finalResolution->mediaItems->first();
                $imagePath = null;
                if ($primaryMedia
                    && in_array($primaryMedia->disk, ['public', 'social_media'], true)
                    && filled($primaryMedia->path)) {
                    $imagePath = $primaryMedia->path;
                }

                $version->update([
                    'image_url' => $finalResolvedUrls[0] ?? null,
                    'image_urls' => $finalResolvedUrls,
                    'image_path' => $imagePath,
                ]);

                $post->current_version_id = $version->id;

                if (! $post->generated_at) {
                    $post->generated_at = now();
                }

                $post->n8n_completed_at = now();
                $post->n8n_error = null;
                $post->status = MarketingCampaignPostStatus::Generated;

                $n8nInternalContext = $post->n8n_internal_context ?? [];
                if (! empty($n8nInternalContext['_internal_temp_logo_path'])) {
                    Storage::disk('public')->delete($n8nInternalContext['_internal_temp_logo_path']);
                    unset($n8nInternalContext['_internal_temp_logo_path']);
                    $post->n8n_internal_context = $n8nInternalContext;
                }

                $post->save();

                return new AddPostVersionResult('created', $version);
            });

            $committed = true;

            try {
                $this->stager->deleteTemporary($temporaryFiles);
            } catch (Throwable $exception) {
                Log::warning('Errore in deleteTemporary dopo commit', ['exception' => $exception->getMessage()]);
            }

            if ($result->wasCreated() && $result->version) {
                try {
                    ($this->automation ?? app(EvaluateMarketingCampaignPostAutomationAction::class))
                        ->execute(
                            MarketingCampaignPost::query()
                                ->with('campaign')
                                ->findOrFail($result->version->marketing_campaign_post_id)
                        );
                } catch (Throwable $exception) {
                    Log::error(
                        'social.automation.callback_evaluation_failed',
                        [
                            'post_id' => $result->version->marketing_campaign_post_id,
                            'version_id' => $result->version->id,
                            'error' => $exception->getMessage(),
                        ]
                    );
                }
            }

            return $result;
        } catch (UniqueConstraintViolationException $exception) {
            $msg = $exception->getMessage();
            if (! str_contains($msg, 'mcpv_n8n_request_unique') &&
                ! str_contains($msg, 'marketing_campaign_post_versions_external_generation_id_unique')) {
                // Non è un duplicato del webhook n8n (es. violazione mcpv_post_version_unique)
                $this->cleanupOnError($temporaryFiles, $promotedFiles, $committed);
                throw $exception;
            }

            // La transazione ha eseguito il rollback. Il recovery query userà la write connection per leggere.
            $result = $this->resolveUniqueViolation($exception, $data);

            $this->cleanupOnError($temporaryFiles, $promotedFiles, $committed);

            if ($result !== null) {
                return $result;
            }

            throw $exception;
        } catch (Throwable $exception) {
            $this->cleanupOnError($temporaryFiles, $promotedFiles, $committed);
            throw $exception;
        }
    }

    private function cleanupOnError(array $temporaryFiles, array $promotedFiles, bool $committed): void
    {
        try {
            $this->stager->deleteTemporary($temporaryFiles);
        } catch (Throwable $e) {
        }

        if (! $committed) {
            try {
                $this->stager->deletePromoted($promotedFiles);
            } catch (Throwable $e) {
            }
        }
    }

    protected function fastCheckDuplicate(AddMarketingCampaignPostVersionData $data): ?AddPostVersionResult
    {
        if ($data->requestId !== null) {
            $existingByReqId = MarketingCampaignPostVersion::where('n8n_request_id', $data->requestId)->first();
            if ($existingByReqId) {
                return new AddPostVersionResult(
                    outcome: $existingByReqId->marketing_campaign_post_id === $data->postId ? 'duplicate' : 'conflict',
                    version: $existingByReqId,
                    reason: $existingByReqId->marketing_campaign_post_id === $data->postId ? 'request_already_processed' : 'request_id_used_by_another_post'
                );
            }
        }

        if ($data->externalGenerationId !== null) {
            $existingByExtId = MarketingCampaignPostVersion::where('external_generation_id', $data->externalGenerationId)->first();
            if ($existingByExtId) {
                return new AddPostVersionResult(
                    outcome: $existingByExtId->marketing_campaign_post_id === $data->postId ? 'duplicate' : 'conflict',
                    version: $existingByExtId,
                    reason: $existingByExtId->marketing_campaign_post_id === $data->postId ? 'external_generation_already_processed' : 'external_generation_used_by_another_post'
                );
            }
        }

        return null;
    }

    private function resolveUniqueViolation(
        UniqueConstraintViolationException $exception,
        AddMarketingCampaignPostVersionData $data,
    ): ?AddPostVersionResult {
        $byRequestId = MarketingCampaignPostVersion::query()
            ->useWritePdo()
            ->where('n8n_request_id', $data->requestId)
            ->first();

        if ($byRequestId) {
            return new AddPostVersionResult(
                outcome: $byRequestId->marketing_campaign_post_id === $data->postId
                    ? 'duplicate'
                    : 'conflict',
                version: $byRequestId,
                reason: $byRequestId->marketing_campaign_post_id === $data->postId
                    ? 'request_already_processed'
                    : 'request_id_used_by_another_post',
            );
        }

        if ($data->externalGenerationId !== null) {
            $byExternalId = MarketingCampaignPostVersion::query()
                ->useWritePdo()
                ->where(
                    'external_generation_id',
                    $data->externalGenerationId
                )
                ->first();

            if ($byExternalId) {
                return new AddPostVersionResult(
                    outcome: $byExternalId->marketing_campaign_post_id === $data->postId
                        ? 'duplicate'
                        : 'conflict',
                    version: $byExternalId,
                    reason: $byExternalId->marketing_campaign_post_id === $data->postId
                        ? 'external_generation_already_processed'
                        : 'external_generation_used_by_another_post',
                );
            }
        }

        return null;
    }
}
