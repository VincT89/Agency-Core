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

        // Qui mappiamo le "capabilities" (can_publish_video, max_video_duration, privacy_levels_supported, etc)
        // NOTA ARCHITETTURALE: /v2/user/info/ ritorna solo i dati base. 
        // Per ottenere i limiti di pubblicazione effettivi, nella FASE 2 dovremo chiamare 
        // l'endpoint del Content Posting API dedicato (es. /v2/post/creator_info/query/)
        // prima di poter riempire correttamente le capabilities reali.
        
        $apiMetadata = $account->api_metadata ?? [];
        $apiMetadata['creator_info'] = $userData;
        
        $publishingCapabilities = $account->publishing_capabilities ?? [];
        // Mappatura hardcoded parziale (il resto verrà risolto in FASE 2)
        $publishingCapabilities['tiktok'] = [
            'can_publish_video' => in_array('video.publish', $account->scopes ?? []),
            // I seguenti campi andranno risolti con query reali nella Fase 2.
            // Li marchiamo provvisori per non dichiarare capabilities non verificate.
            'max_video_duration' => null, 
            'privacy_levels_supported' => null,
            'commercial_content_allowed' => null,
            'publish_mode' => 'unknown', 
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
