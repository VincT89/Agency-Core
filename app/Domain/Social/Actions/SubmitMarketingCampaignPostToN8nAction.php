<?php

namespace App\Domain\Social\Actions;

use App\Models\MarketingCampaignPost;
use App\Jobs\SendMarketingCampaignPostToN8nJob;
use App\Domain\Social\Builders\MarketingCampaignPostMediaPayloadBuilder;
use Illuminate\Support\Str;

class SubmitMarketingCampaignPostToN8nAction
{
    public function execute(MarketingCampaignPost $post, array $runtimeClientData = []): void
    {
        // 1. File I/O fuori dalla transazione
        $campaign = $post->campaign;
        $client = $campaign->client;

        $includeLogo = $runtimeClientData['include_client_logo'] ?? false;
        $logoUrl = null;
        $tempPathToDelete = null;
        $savedToClientLogo = false;
        $clientLogoPathToSave = null;

        if ($includeLogo) {
            if ($client->logo_path) {
                $logoUrl = $client->logo_url;
            } elseif (!empty($runtimeClientData['runtime_logo'])) {
                $runtimeLogoFile = $runtimeClientData['runtime_logo'];
                
                if ($runtimeLogoFile instanceof \Illuminate\Http\UploadedFile) {
                    $filename = 'temp_logo_' . time() . '.' . $runtimeLogoFile->getClientOriginalExtension();
                    
                    if (!empty($runtimeClientData['save_runtime_logo_to_client'])) {
                        $runtimeLogoFile->storeAs('clients/logos', $filename, 'public');
                        $clientLogoPathToSave = 'clients/logos/' . $filename;
                        $savedToClientLogo = true;
                    } else {
                        $path = $runtimeLogoFile->storeAs('clients/logos/temp', $filename, 'public');
                        $tempPathToDelete = $path;
                        $logoUrl = route('media.public', ['path' => $path]);
                    }
                } elseif (is_string($runtimeLogoFile)) {
                    $logoUrl = $runtimeLogoFile;
                }
            }
        }

        // 2. Transazione DB per gestire lo stato del post
        $jobData = \Illuminate\Support\Facades\DB::transaction(function () use (&$post, $runtimeClientData, $client, $includeLogo, $logoUrl, $tempPathToDelete, $savedToClientLogo, $clientLogoPathToSave, $campaign) {
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

            // Applica le modifiche al Client derivate dall'I/O fuori transazione
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

            // Genera Request ID per idempotenza se non esiste
            if (!$post->n8n_request_id) {
                $post->n8n_request_id = 'cmp_' . Str::uuid()->toString();
            }

            // Riusa il payload esistente se c'è, altrimenti ricostruisci
            if (!empty($post->approved_payload_snapshot)) {
                $payload = $post->approved_payload_snapshot;
            } else {
                $clientPayload = [
                    'id' => $client->id,
                    'name' => $client->name,
                    'logo_url' => $logoUrl,
                    'activity_description' => $activityDescription,
                ];

                $mediaPayload = MarketingCampaignPostMediaPayloadBuilder::build($post);

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
                ];
            }

            $n8nInternalContext = [
                'include_client_logo' => $includeLogo,
                'include_client_header' => $includeHeader,
                'save_runtime_logo_to_client' => $savedToClientLogo,
                'save_runtime_activity_to_client' => $savedToClientActivity,
                '_internal_temp_logo_path' => $tempPathToDelete,
            ];

            // Salva stato, payload e contesto interno
            $previousStatus = $post->status;
            $post->update([
                'n8n_previous_status' => $previousStatus->value,
                'status' => \App\Enums\Social\MarketingCampaignPostStatus::PendingN8n->value,
                'approved_payload_snapshot' => $payload,
                'n8n_payload_hash' => hash('sha256', json_encode($payload)),
                'n8n_internal_context' => $n8nInternalContext,
            ]);

            return [
                'payload' => $payload,
                'tempPathToDelete' => $tempPathToDelete,
                'savedToClientLogo' => $savedToClientLogo,
            ];
        });

        // 3. Dispatch del Job fuori dalla transazione DB
        if ($jobData) {
            SendMarketingCampaignPostToN8nJob::dispatch($post, $jobData['payload'], $jobData['tempPathToDelete'], $jobData['savedToClientLogo']);
        }
    }
}
