<?php

namespace App\Domain\Social\TikTok;

use App\Models\ClientSocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TikTokCreatorInfoService
{
    public function updateCreatorInfo(ClientSocialAccount $account): bool
    {
        if ($account->platform !== \App\Enums\Social\SocialPlatform::Tiktok) {
            return false;
        }

        if (!$account->access_token) {
            return false;
        }

        $apiBase = config('services.tiktok.api_base', 'https://open.tiktokapis.com');

        // endpoint per recuperare creator info (dipende dalle versioni API di TikTok)
        // per v2: /v2/user/info/
        // Usiamo i field che interessano a noi, ad esempio per creator_info
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $account->access_token,
        ])->get("{$apiBase}/v2/user/info/", [
            'fields' => 'open_id,union_id,avatar_url,display_name'
        ]);

        if ($response->failed()) {
            Log::error('TikTok User Info Fetch Failed', [
                'account_id' => $account->id,
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return false;
        }

        $data = $response->json();
        $userData = $data['data']['user'] ?? [];

        $scopes = $account->scopes ?? [];

        $canUploadVideoDraft = in_array('video.upload', $scopes, true);
        $canDirectPublishVideo = in_array('video.publish', $scopes, true);

        // NOTA ARCHITETTURALE: Recuperiamo le capability reali dal Content Posting API mock
        $contentService = app(\App\Domain\Social\TikTok\TikTokContentPostingService::class);
        $contentInfo = [];
        
        if ($canDirectPublishVideo) {
            try {
                $contentInfo = $contentService->queryCreatorInfo($account->access_token, (string) $account->id);
            } catch (\Exception $e) {
                Log::warning('TikTok queryCreatorInfo Failed', [
                    'account_id' => $account->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        $apiMetadata = $account->api_metadata ?? [];
        $apiMetadata['creator_info'] = $userData;
        if (!empty($contentInfo)) {
            $apiMetadata['content_posting_info'] = $contentInfo;
        }
        
        $publishingCapabilities = $account->publishing_capabilities ?? [];
        $publishMode = config('services.tiktok.delivery_mode', 'disabled');
        
        $publishingCapabilities['tiktok'] = [
            'can_upload_video_draft' => $canUploadVideoDraft,
            'can_direct_publish_video' => $canDirectPublishVideo,
            // Alias interno: significa "può usare il flusso video configurato"
            'can_publish_video' => config('services.tiktok.delivery_mode') === 'draft'
                ? $canUploadVideoDraft
                : $canDirectPublishVideo,
            'max_video_duration' => $contentInfo['max_video_post_duration_sec'] ?? null, 
            'privacy_levels_supported' => $contentInfo['privacy_level_options'] ?? null,
            'commercial_content_allowed' => null,
            'delivery_mode' => $publishMode, 
        ];

        $account->update([
            'account_name' => $userData['display_name'] ?? $account->account_name,
            'api_metadata' => $apiMetadata,
            'publishing_capabilities' => $publishingCapabilities,
            'last_api_check_at' => now(),
        ]);

        return true;
    }
}

