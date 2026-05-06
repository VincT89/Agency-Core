<?php

namespace App\Domain\Social\Actions;

use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostVersion;
use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\Social\MarketingCampaignPostRegenerationType;
use App\Enums\Social\MarketingCampaignPostVersionSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class AddMarketingCampaignPostVersionFromN8nAction
{
    public function execute(MarketingCampaignPost $post, array $data): MarketingCampaignPostVersion
    {
        return DB::transaction(function () use ($post, $data) {
            // Lock the post
            $post = MarketingCampaignPost::where('id', $post->id)->lockForUpdate()->firstOrFail();

            // Check if external generation id already exists for idempotency
            if (!empty($data['external_generation_id'])) {
                $existingVersion = MarketingCampaignPostVersion::where('external_generation_id', $data['external_generation_id'])->first();
                if ($existingVersion) {
                    return $existingVersion;
                }
            }

            $currentVersion = $post->currentVersion;
            $versionNumber = $post->versions()->max('version_number') + 1;
            $regenerationType = MarketingCampaignPostRegenerationType::tryFrom($data['regeneration_type'] ?? 'full') ?? MarketingCampaignPostRegenerationType::Full;

            $versionData = [
                'marketing_campaign_post_id' => $post->id,
                'created_by' => null, // AI created
                'version_number' => $versionNumber,
                'regeneration_type' => $regenerationType->value,
                'source' => MarketingCampaignPostVersionSource::N8n->value,
                'external_generation_id' => $data['external_generation_id'] ?? null,
                'prompt_used' => $data['prompt_used'] ?? null,
                'raw_payload' => $data['raw_payload'] ?? $data,
            ];

            if ($regenerationType === MarketingCampaignPostRegenerationType::Caption) {
                // Eredita immagine dalla versione corrente
                $versionData['title'] = $data['title'] ?? null;
                $versionData['caption'] = $data['caption'] ?? null;
                $versionData['hashtags'] = $data['hashtags'] ?? null;
                
                $versionData['image_url'] = $currentVersion?->image_url;
                $versionData['image_path'] = $currentVersion?->image_path;
            } elseif ($regenerationType === MarketingCampaignPostRegenerationType::Image) {
                // Eredita testo dalla versione corrente
                $versionData['title'] = $currentVersion?->title;
                $versionData['caption'] = $currentVersion?->caption;
                $versionData['hashtags'] = $currentVersion?->hashtags;
                
                $versionData['image_url'] = $data['image_url'] ?? null;
                $versionData['image_path'] = null; // Gestire download dell'immagine se necessario
            } else {
                // Full
                $versionData['title'] = $data['title'] ?? null;
                $versionData['caption'] = $data['caption'] ?? null;
                $versionData['hashtags'] = $data['hashtags'] ?? null;
                
                $versionData['image_url'] = $data['image_url'] ?? null;
                $versionData['image_path'] = null;
            }

            $version = MarketingCampaignPostVersion::create($versionData);

            // Update Post
            $post->current_version_id = $version->id;
            
            if (!$post->generated_at) {
                $post->generated_at = now();
            }
            
            $post->n8n_completed_at = now();
            $post->n8n_error = null;
            
            // Set status: if client approval is needed, ready_for_client, else generated?
            // "status = generated oppure ready_for_client"
            // For now, let's use Generated. ReadyForClient can be a manual step.
            $post->status = MarketingCampaignPostStatus::Generated;
            
            // Clean up temp logo path if it was saved internally
            $n8nInternalContext = $post->n8n_internal_context ?? [];
            if (!empty($n8nInternalContext['_internal_temp_logo_path'])) {
                Storage::disk('public')->delete($n8nInternalContext['_internal_temp_logo_path']);
                unset($n8nInternalContext['_internal_temp_logo_path']);
                $post->n8n_internal_context = $n8nInternalContext;
            }

            $post->save();

            return $version;
        });
    }
}
