<?php

namespace App\Domain\Social\Services;

use App\Models\MarketingCampaignPost;
use App\Models\ClientSocialAccount;
use App\Domain\Social\Publishing\TikTokPublisher;
use Illuminate\Support\Facades\Cache;

class TikTokPreflightService
{
    public function __construct(
        private TikTokPublisher $publisher,
        private SocialMediaPublicUrlService $mediaUrlService,
        private TikTokTokenRefreshService $tokenRefreshService
    ) {}

    public function runPreflight(MarketingCampaignPost $post, ClientSocialAccount $account): PreflightResult
    {
        $result = new PreflightResult(true);

        if ($account->platform->value !== 'tiktok') {
            $result->addCheck('platform', false, 'Piattaforma non valida per TikTokPreflightService.');
            return $result;
        }

        // 1. Validazione e Refresh Token
        $isTokenValid = $this->tokenRefreshService->ensureValidToken($account);
        $result->addCheck('token_valid', $isTokenValid, $isTokenValid ? null : 'Token TikTok scaduto o non valido e refresh fallito. Ricollegare l\'account.');

        if (!$isTokenValid) {
            return $result;
        }

        // 2. Validazione Capabilities (con Cache per non abusare dell'API)
        $hasCapabilities = $this->publisher->verifyAccountCapabilities($account);
        $result->addCheck('account_capabilities', $hasCapabilities, $hasCapabilities ? null : 'Account non configurato per la pubblicazione API o capabilities mancanti.');

        if (!$hasCapabilities) {
            return $result;
        }

        // 3. Controllo Media
        $mediaItems = $post->orderedMediaItems;
        $hasMedia = $mediaItems->count() > 0;
        
        $result->addCheck('media_present', $hasMedia, $hasMedia ? null : 'TikTok richiede almeno un file multimediale (Video o Foto).');

        if ($hasMedia) {
            $hasVideo = $mediaItems->contains(function ($media) {
                return strtolower($media->media_type ?? '') === 'video' || in_array(strtolower(pathinfo($media->path ?? '', PATHINFO_EXTENSION)), ['mp4', 'mov', 'webm']);
            });
            $hasPhoto = $mediaItems->contains(function ($media) {
                return strtolower($media->media_type ?? '') === 'image' || in_array(strtolower(pathinfo($media->path ?? '', PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png']);
            });

            // No mixed media
            $result->addCheck('no_mixed_media', !($hasVideo && $hasPhoto), !($hasVideo && $hasPhoto) ? null : 'TikTok non supporta media misti. Carica solo un video o un set di foto.');

            if ($hasVideo) {
                $result->addCheck('single_video_only', $mediaItems->count() === 1, $mediaItems->count() === 1 ? null : 'TikTok supporta solo 1 video per post.');

                $media = $mediaItems->first();
                $ext = strtolower(pathinfo($media->path ?? '', PATHINFO_EXTENSION));
                $mime = $media->mime_type ?? '';
                
                // Formati ammessi da TikTok API (MP4, WebM)
                $isValidVideoFormat = in_array($ext, ['mp4', 'webm', 'mov']) || in_array($mime, ['video/mp4', 'video/webm', 'video/quicktime']);
                $result->addCheck('video_format', $isValidVideoFormat, $isValidVideoFormat ? null : "Formato video non supportato da TikTok. Richiesto MP4 o WebM.");
                
                // Limite peso 500MB (pratico per PullFromUrl)
                $sizeMB = $media->size / 1024 / 1024;
                $isUnderVideoLimit = $sizeMB <= 500;
                $result->addCheck('video_size', $isUnderVideoLimit, $isUnderVideoLimit ? null : "Il video supera il limite dimensionale supportato per l'upload (500MB).");

            } elseif ($hasPhoto) {
                // Max 10 (o max_photo_count da capabilities)
                $maxPhotoCount = $account->publishing_capabilities['tiktok']['max_photo_count'] ?? config('services.tiktok.max_photo_count', 10);
                $isUnderCountLimit = $mediaItems->count() <= $maxPhotoCount;
                $result->addCheck('photo_count_limit', $isUnderCountLimit, $isUnderCountLimit ? null : "Superato il limite massimo di foto consentito per TikTok ({$maxPhotoCount}).");
            }

            // Check URL generabile/pubblico
            foreach ($mediaItems as $media) {
                try {
                    $this->mediaUrlService->getValidatedPublicUrl($media);
                    $result->addCheck("media_url_{$media->id}", true);
                } catch (\Exception $e) {
                    $result->addCheck("media_url_{$media->id}", false, "Errore risoluzione media '{$media->original_name}': " . $e->getMessage());
                }
            }
        }

        return $result;
    }
}
