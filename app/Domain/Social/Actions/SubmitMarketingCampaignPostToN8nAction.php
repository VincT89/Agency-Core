<?php

namespace App\Domain\Social\Actions;

use App\Models\MarketingCampaignPost;
use App\Jobs\SendMarketingCampaignPostToN8nJob;
use Illuminate\Support\Str;

class SubmitMarketingCampaignPostToN8nAction
{
    public function execute(MarketingCampaignPost $post, array $runtimeClientData = []): void
    {
        // Genera Request ID per idempotenza se non esiste
        if (!$post->n8n_request_id) {
            $post->n8n_request_id = 'cmp_' . Str::uuid()->toString();
        }

        $campaign = $post->campaign;
        $client = $campaign->client;

        // Sicurezza: l'action decide logo e activity
        $includeLogo = $runtimeClientData['include_client_logo'] ?? false;
        $logoUrl = null;
        $tempPathToDelete = null;
        $savedToClientLogo = false;

        if ($includeLogo) {
            if ($client->logo_path) {
                $logoUrl = $client->logo_url;
            } elseif (!empty($runtimeClientData['runtime_logo'])) {
                $runtimeLogoFile = $runtimeClientData['runtime_logo'];
                
                if ($runtimeLogoFile instanceof \Illuminate\Http\UploadedFile) {
                    $filename = 'temp_logo_' . time() . '.' . $runtimeLogoFile->getClientOriginalExtension();
                    
                    if (!empty($runtimeClientData['save_runtime_logo_to_client'])) {
                        $runtimeLogoFile->storeAs('clients/logos', $filename, 'public');
                        $client->update(['logo_path' => 'clients/logos/' . $filename]);
                        $logoUrl = $client->logo_url;
                        $savedToClientLogo = true;
                    } else {
                        $path = $runtimeLogoFile->storeAs('clients/logos/temp', $filename, 'public');
                        $tempPathToDelete = $path;
                        $logoUrl = route('media.public', ['path' => $path]);
                    }
                } elseif (is_string($runtimeLogoFile)) {
                    $logoUrl = $runtimeLogoFile;
                }
            } else {
                $includeLogo = false; // fallback automatico
            }
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
            } else {
                $includeHeader = false;
            }
        }

        // Payload N8n pulito: solo i dati finali che il tutor deve effettivamente usare
        $clientPayload = [
            'id' => $client->id,
            'name' => $client->name,
            'logo_url' => $logoUrl,
            'activity_description' => $activityDescription,
        ];

        $mediaUrl = $post->media_url;

        // Costruisci il payload
        $payload = [
            'type' => 'marketing_campaign_post',
            'request_id' => $post->n8n_request_id,
            'campaign' => [
                'id' => $campaign->id,
                'name' => $campaign->name,
            ],
            'client' => $clientPayload,
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'description' => $post->description,
                'content_type' => $post->content_type->value,
                'scheduled_date' => $post->scheduled_date ? $post->scheduled_date->format('Y-m-d') : null,
                'scheduled_time' => $post->scheduled_time ? date('H:i', strtotime($post->scheduled_time)) : null,
                'ai_analysis_enabled' => $post->ai_analysis_enabled,
                'media_url' => $mediaUrl,
                'media' => [
                    'source' => $post->media_source,
                    'url' => $mediaUrl,
                    'nextcloud_path' => $post->nextcloud_path,
                    'nextcloud_share_url' => $post->nextcloud_share_url,
                    'nextcloud_file_id' => $post->nextcloud_file_id,
                ],
            ],
            'callback_url' => route('api.v1.integrations.n8n.marketing-campaign-posts.versions.store', $post),
        ];

        $n8nInternalContext = [
            'include_client_logo' => $includeLogo,
            'include_client_header' => $includeHeader,
            'save_runtime_logo_to_client' => $savedToClientLogo,
            'save_runtime_activity_to_client' => $savedToClientActivity,
            '_internal_temp_logo_path' => $tempPathToDelete,
        ];

        // Salva stato, payload pulito e contesto interno
        $post->update([
            'status' => \App\Enums\Social\MarketingCampaignPostStatus::PendingN8n->value,
            'n8n_payload' => $payload,
            'n8n_internal_context' => $n8nInternalContext,
        ]);

        // Dispatch del Job con eventuale path temporaneo da cancellare post invio (in caso di fail definitivo)
        SendMarketingCampaignPostToN8nJob::dispatch($post, $payload, $tempPathToDelete, $savedToClientLogo);
    }
}
