<?php

namespace App\Domain\Social\Actions;

use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostVersion;
use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\Social\MarketingCampaignPostRegenerationType;
use App\Enums\Social\MarketingCampaignPostVersionSource;
use App\Domain\Social\DTOs\AddMarketingCampaignPostVersionData;
use App\Domain\Social\DTOs\AddPostVersionResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\UniqueConstraintViolationException;
use Throwable;

class AddMarketingCampaignPostVersionFromN8nAction
{
    public function __construct(
        private readonly \App\Domain\Social\Services\ImageStagerService $stager
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

                // request ID non più atteso perché sostituito da una nuova richiesta (per lo stesso post)
                if ($data->requestId !== null && $post->n8n_request_id !== null && $post->n8n_request_id !== $data->requestId) {
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
                    $versionData['image_url'] = $currentVersion?->image_url;
                    $versionData['image_urls'] = $currentVersion?->image_urls;
                    $versionData['image_path'] = $currentVersion?->image_path;
                } elseif ($data->regenerationType === MarketingCampaignPostRegenerationType::Image) {
                    $versionData['title'] = $currentVersion?->title;
                    $versionData['caption'] = $currentVersion?->caption;
                    $versionData['hashtags'] = $currentVersion?->hashtags;
                    $versionData['image_url'] = count($data->imageUrls ?? []) === 1 ? $data->imageUrls[0] : null;
                    $versionData['image_urls'] = $data->imageUrls;
                    $versionData['image_path'] = count($promotedFiles) > 0 ? $promotedFiles[0] : null;
                } else {
                    $versionData['title'] = $data->title;
                    $versionData['caption'] = $data->caption;
                    $versionData['hashtags'] = $data->hashtags;
                    $versionData['image_url'] = count($data->imageUrls ?? []) === 1 ? $data->imageUrls[0] : null;
                    $versionData['image_urls'] = $data->imageUrls;
                    $versionData['image_path'] = count($promotedFiles) > 0 ? $promotedFiles[0] : null;
                }

                $version = MarketingCampaignPostVersion::create($versionData);

                // --- GESTIONE MEDIA E PIVOT ---
                if ($data->regenerationType === MarketingCampaignPostRegenerationType::Caption) {
                    // Rigenerazione solo caption: copia le associazioni pivot della versione precedente.
                    // Se la versione precedente non ha associazioni pivot, usa il fallback legacy.
                    $sourceMedia = collect();
                    if ($currentVersion && $currentVersion->mediaItems()->exists()) {
                        $sourceMedia = $currentVersion->mediaItems;
                    } elseif ($currentVersion || $post->mediaItems()->exists()) {
                        // Fallback legacy dal post
                        $sourceMedia = $post->orderedMediaItems;
                    }

                    $pivotData = [];
                    foreach ($sourceMedia as $index => $media) {
                        // Se c'era un sort_order sul pivot, in fallback mettiamo $index
                        $sortOrder = $media->pivot->sort_order ?? $index;
                        $pivotData[$media->id] = ['sort_order' => $sortOrder];
                    }
                    if (!empty($pivotData)) {
                        $version->mediaItems()->attach($pivotData);
                    }
                } else {
                    // Rigenerazione image o full: crea nuovi media e associazioni pivot.
                    $pivotData = [];
                    foreach ($promotedFiles as $index => $promotedPath) {
                        $mime = 'unknown';
                        if (Storage::disk('public')->exists($promotedPath)) {
                            $mime = Storage::disk('public')->mimeType($promotedPath);
                        }

                        $media = \App\Models\MarketingCampaignPostMedia::create([
                            'marketing_campaign_post_id' => $post->id,
                            'path' => $promotedPath,
                            'disk' => 'public',
                            'source' => 'n8n',
                            'mime_type' => $mime,
                            'media_type' => \App\Models\MarketingCampaignPostMedia::detectMediaType($mime),
                            'sort_order' => $index, // Fallback order per il post
                        ]);

                        $pivotData[$media->id] = ['sort_order' => $index];
                    }
                    if (!empty($pivotData)) {
                        $version->mediaItems()->attach($pivotData);
                    }
                }

                $post->current_version_id = $version->id;
                
                if (!$post->generated_at) {
                    $post->generated_at = now();
                }
                
                $post->n8n_completed_at = now();
                $post->n8n_error = null;
                $post->status = MarketingCampaignPostStatus::Generated;
                
                $n8nInternalContext = $post->n8n_internal_context ?? [];
                if (!empty($n8nInternalContext['_internal_temp_logo_path'])) {
                    Storage::disk('public')->delete($n8nInternalContext['_internal_temp_logo_path']);
                    unset($n8nInternalContext['_internal_temp_logo_path']);
                    $post->n8n_internal_context = $n8nInternalContext;
                }

                $post->save();

                return new AddPostVersionResult('created', $version);
            });

            $this->stager->deleteTemporary($temporaryFiles);

            return $result;
        } catch (UniqueConstraintViolationException $exception) {
            $msg = $exception->getMessage();
            if (!str_contains($msg, 'mcpv_n8n_request_unique') && 
                !str_contains($msg, 'marketing_campaign_post_versions_external_generation_id_unique')) {
                // Non è un duplicato del webhook n8n (es. violazione mcpv_post_version_unique)
                $this->cleanupOnError($temporaryFiles, $promotedFiles);
                throw $exception;
            }

            // La transazione ha eseguito il rollback. Il recovery query userà la write connection per leggere.
            $result = $this->resolveUniqueViolation($exception, $data);

            $this->cleanupOnError($temporaryFiles, $promotedFiles);

            if ($result !== null) {
                return $result;
            }

            throw $exception;
        } catch (Throwable $exception) {
            $this->cleanupOnError($temporaryFiles, $promotedFiles);
            throw $exception;
        }
    }

    private function cleanupOnError(array $temporaryFiles, array $promotedFiles): void
    {
        $this->stager->deleteTemporary($temporaryFiles);
        $this->stager->deletePromoted($promotedFiles);
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
