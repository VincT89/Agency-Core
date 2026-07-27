<?php

namespace App\Domain\Social\Actions;

use App\Models\MarketingCampaignPost;
use App\Jobs\SendMarketingCampaignPostToN8nJob;
use App\Domain\Social\Builders\MarketingCampaignPostMediaPayloadBuilder;
use Illuminate\Support\Str;

class SubmitMarketingCampaignPostToN8nAction
{
    public function __construct(
        private readonly \App\Domain\Social\Builders\MarketingCampaignPostMediaPayloadBuilder $builder
    ) {}

    public function execute(MarketingCampaignPost $post, array $runtimeClientData = []): void
    {
        $campaign = $post->campaign;
        $client = $campaign->client;

        $includeLogo = $runtimeClientData['include_client_logo'] ?? false;
        $logoUrl = null;
        $tempPathToDelete = null;
        $savedToClientLogo = false;
        $clientLogoPathToSave = null;
        $runtimeLogoPath = null;

        try {
            $jobData = \Illuminate\Support\Facades\DB::transaction(function () use (&$post, $runtimeClientData, $client, $includeLogo, &$logoUrl, &$tempPathToDelete, &$savedToClientLogo, &$clientLogoPathToSave, &$runtimeLogoPath, $campaign) {
                $post = MarketingCampaignPost::lockForUpdate()->findOrFail($post->id);

                // Evita invii se il post è già in stato finale
                if (in_array($post->status, [
                    \App\Enums\Social\MarketingCampaignPostStatus::ClientApproved,
                    \App\Enums\Social\MarketingCampaignPostStatus::Approved,
                    \App\Enums\Social\MarketingCampaignPostStatus::Published,
                    \App\Enums\Social\MarketingCampaignPostStatus::Cancelled,
                ])) {
                    return null;
                }

                // Evita invii duplicati se è già pending o sta rigenerando
                if (in_array($post->status, [
                    \App\Enums\Social\MarketingCampaignPostStatus::PendingN8n,
                    \App\Enums\Social\MarketingCampaignPostStatus::SubmittedToN8n,
                    \App\Enums\Social\MarketingCampaignPostStatus::Regenerating,
                ])) {
                    return null;
                }

                // 1. Build payload prima del logo e dentro al lock
                $mediaPayload = $this->builder->build($post);

                // 2. Salva logo runtime solo se il builder ha successo
                if ($includeLogo) {
                    if ($client->logo_path) {
                        $logoUrl = $client->logo_url;
                    } elseif (!empty($runtimeClientData['runtime_logo'])) {
                        $runtimeLogoFile = $runtimeClientData['runtime_logo'];
                        
                        if ($runtimeLogoFile instanceof \Illuminate\Http\UploadedFile) {
                            $filename = 'temp_logo_' . Str::uuid()->toString() . '.' . $runtimeLogoFile->getClientOriginalExtension();
                            
                            if (!empty($runtimeClientData['save_runtime_logo_to_client'])) {
                                $runtimeLogoFile->storeAs('clients/logos', $filename, 'public');
                                $clientLogoPathToSave = 'clients/logos/' . $filename;
                                $runtimeLogoPath = $clientLogoPathToSave;
                                $savedToClientLogo = true;
                            } else {
                                $path = $runtimeLogoFile->storeAs('clients/logos/temp', $filename, 'public');
                                $tempPathToDelete = $path;
                                $runtimeLogoPath = $path;
                                $logoUrl = route('media.public', ['path' => $path]);
                            }
                        } elseif (is_string($runtimeLogoFile)) {
                            $logoUrl = $runtimeLogoFile;
                        }
                    }
                }

                // Applica le modifiche al Client
                if ($clientLogoPathToSave) {
                    $client->update(['logo_path' => $clientLogoPathToSave]);
                    $logoUrl = $client->logo_url;
                }

                $includeHeader = $runtimeClientData['include_client_header'] ?? false;
                $activityDescription = null;
                $savedToClientActivity = false;

                if ($includeHeader) {
                    if ($client->activity_description) {
                        $activityDescription = $client->activity_description;
                    } elseif (!empty($runtimeClientData['runtime_activity_description'])) {
                        $activityDescription = $runtimeClientData['runtime_activity_description'];
                        
                        if (!empty($runtimeClientData['save_runtime_activity_to_client'])) {
                            $client->update(['activity_description' => $activityDescription]);
                            $savedToClientActivity = true;
                        }
                    }
                }

                // Genera Request ID nuovo per ogni invio
                $oldRequestId = $post->n8n_request_id;
                $post->n8n_request_id = 'cmp_' . Str::uuid()->toString();

                $clientPayload = [
                    'id' => $client->id,
                    'name' => $client->name,
                    'logo_url' => $logoUrl,
                    'activity_description' => $activityDescription,
                ];

                $payload = [
                    'type' => 'marketing_campaign_post',
                    'request_id' => $post->n8n_request_id,
                    'campaign' => [
                        'id' => $campaign->id,
                        'name' => $campaign->name,
                    ],
                    'client' => $clientPayload,
                    'post' => array_merge([
                        'id' => $post->id,
                        'title' => $post->title,
                        'description' => $post->description,
                        'content_type' => $post->content_type->value,
                        'scheduled_date' => $post->scheduled_date ? $post->scheduled_date->format('Y-m-d') : null,
                        'scheduled_time' => $post->scheduled_time ? date('H:i', strtotime($post->scheduled_time)) : null,
                        'ai_analysis_enabled' => $post->ai_analysis_enabled,
                        'publishing_platforms' => $post->publishing_platforms ?? [],
                    ], $mediaPayload),
                    'callback_url' => route('api.v1.integrations.n8n.marketing-campaign-posts.versions.store', $post),
                    'failed_callback_url' => route('api.v1.integrations.n8n.marketing-campaign-posts.failed', $post),
                ];

                if (!empty($runtimeClientData['generation_type'])) {
                    $payload['generation_type'] = $runtimeClientData['generation_type'];
                }

                $n8nInternalContext = [
                    'include_client_logo' => $includeLogo,
                    'include_client_header' => $includeHeader,
                    'save_runtime_logo_to_client' => $savedToClientLogo,
                    'save_runtime_activity_to_client' => $savedToClientActivity,
                    '_internal_temp_logo_path' => $tempPathToDelete,
                ];

                // Salva stato, payload e contesto interno
                $previousState = [
                    'n8n_request_id' => $oldRequestId,
                    'status' => $post->status->value,
                    'n8n_previous_status' => $post->n8n_previous_status,
                    'n8n_error' => $post->n8n_error,
                    'submitted_to_n8n_at' => $post->submitted_to_n8n_at,
                    'n8n_completed_at' => $post->n8n_completed_at,
                    'approved_payload_snapshot' => $post->approved_payload_snapshot,
                    'n8n_payload_hash' => $post->n8n_payload_hash,
                    'n8n_internal_context' => $post->n8n_internal_context,
                ];

                $post->update([
                    'n8n_previous_status' => $previousState['status'],
                    'status' => \App\Enums\Social\MarketingCampaignPostStatus::PendingN8n->value,
                    'approved_payload_snapshot' => $payload,
                    'n8n_payload_hash' => hash('sha256', json_encode($payload)),
                    'n8n_internal_context' => $n8nInternalContext,
                    'n8n_error' => null,
                    'submitted_to_n8n_at' => null,
                    'n8n_completed_at' => null,
                ]);

                return [
                    'payload' => $payload,
                    'tempPathToDelete' => $tempPathToDelete,
                    'savedToClientLogo' => $savedToClientLogo,
                    'previousState' => $previousState,
                ];
            });
        } catch (\Throwable $exception) {
            if ($runtimeLogoPath !== null) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($runtimeLogoPath);
            }
            throw $exception;
        }

        // 3. Dispatch del Job fuori dalla transazione DB
        if ($jobData) {
            try {
                SendMarketingCampaignPostToN8nJob::dispatch($post, $jobData['payload'], $jobData['tempPathToDelete'], $jobData['savedToClientLogo'], $jobData['previousState']);
            } catch (\Throwable $dispatchException) {
                $fileToDelete = null;

                try {
                    \Illuminate\Support\Facades\DB::transaction(function () use ($post, $jobData, &$fileToDelete) {
                        $lockedPost = MarketingCampaignPost::lockForUpdate()->findOrFail($post->id);

                        if ($lockedPost->n8n_request_id === $jobData['payload']['request_id'] 
                            && $lockedPost->status->value === \App\Enums\Social\MarketingCampaignPostStatus::PendingN8n->value
                        ) {
                            $lockedPost->update([
                                'status' => $jobData['previousState']['status'],
                                'n8n_previous_status' => $jobData['previousState']['n8n_previous_status'],
                                'n8n_request_id' => $jobData['previousState']['n8n_request_id'],
                                'approved_payload_snapshot' => $jobData['previousState']['approved_payload_snapshot'],
                                'n8n_payload_hash' => $jobData['previousState']['n8n_payload_hash'],
                                'n8n_internal_context' => $jobData['previousState']['n8n_internal_context'],
                                'n8n_error' => $jobData['previousState']['n8n_error'],
                                'submitted_to_n8n_at' => $jobData['previousState']['submitted_to_n8n_at'],
                                'n8n_completed_at' => $jobData['previousState']['n8n_completed_at'],
                            ]);

                            if (!empty($jobData['tempPathToDelete'])) {
                                $fileToDelete = $jobData['tempPathToDelete'];
                            }
                        }
                    });
                } catch (\Throwable $compensationException) {
                    \Illuminate\Support\Facades\Log::error('Impossibile ripristinare il DB dopo il dispatch fallito di n8n', [
                        'post_id' => $post->id,
                        'error' => $compensationException->getMessage()
                    ]);
                }

                try {
                    if ($fileToDelete) {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($fileToDelete);
                    }
                } catch (\Throwable $cleanupException) {
                    \Illuminate\Support\Facades\Log::error('Impossibile rimuovere il file locale temporaneo dopo il dispatch fallito', [
                        'path' => $fileToDelete,
                        'error' => $cleanupException->getMessage()
                    ]);
                }

                throw $dispatchException;
            }
        }
    }
}
