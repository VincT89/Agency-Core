<?php

namespace App\Domain\Social\TikTok;

use App\Enums\Social\SocialPlatform;
use App\Models\ClientSocialAccount;
use App\Support\Http\ProviderErrorSanitizer;
use App\Support\Http\SocialProviderHttp;
use Illuminate\Support\Facades\Log;

class TikTokCreatorInfoService
{
    public function updateCreatorInfo(ClientSocialAccount $account): bool
    {
        if ($account->platform !== SocialPlatform::Tiktok) {
            return false;
        }

        if (! $account->access_token) {
            return false;
        }

        $apiBase = config('services.tiktok.api_base', 'https://open.tiktokapis.com');

        // endpoint per recuperare creator info (dipende dalle versioni API di TikTok)
        // per v2: /v2/user/info/
        // Usiamo i field che interessano a noi, ad esempio per creator_info
        try {
            $response = SocialProviderHttp::tiktok(retrySafe: true)->withHeaders([
                'Authorization' => 'Bearer '.$account->access_token,
            ])->get("{$apiBase}/v2/user/info/", [
                'fields' => 'open_id,union_id,avatar_url,display_name',
            ]);
        } catch (\Throwable $e) {
            Log::error('TikTok User Info Fetch Exception', [
                'account_id' => $account->id,
                'exception' => $e::class,
            ]);

            return false;
        }

        if ($response->failed()) {
            Log::error('TikTok User Info Fetch Failed', [
                'account_id' => $account->id,
                ...ProviderErrorSanitizer::context($response),
            ]);

            return false;
        }

        $data = $response->json();
        $userData = $data['data']['user'] ?? [];

        $scopes = $account->scopes ?? [];

        $canUploadVideoDraft = in_array('video.upload', $scopes, true);
        $canDirectPublishVideo = in_array('video.publish', $scopes, true);

        // NOTA ARCHITETTURALE: Recuperiamo le capability reali dal Content Posting API mock
        $contentService = app(TikTokContentPostingService::class);
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
        if (! empty($contentInfo)) {
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
            'can_publish_photo' => config('services.tiktok.enable_photo_mode', false)
                && (
                    $publishMode === 'draft'
                        ? $canUploadVideoDraft
                        : $canDirectPublishVideo
                ),
            'supports_photo_mode' => config('services.tiktok.enable_photo_mode', false),
            'max_photo_count' => (int) config('services.tiktok.max_photo_count', 10),
            'commercial_content_allowed' => null,
            'delivery_mode' => $publishMode,
        ];

        $account->update([
            'account_name' => $userData['display_name'] ?? $account->account_name,
            'username' => filled($contentInfo['creator_username'] ?? null)
                ? ltrim(trim((string) $contentInfo['creator_username']), '@')
                : $account->username,
            'api_metadata' => $apiMetadata,
            'publishing_capabilities' => $publishingCapabilities,
            'last_api_check_at' => now(),
        ]);

        return true;
    }
}
