<?php

namespace App\Domain\Social\Services;

use App\Models\MarketingCampaignPost;
use App\Models\ClientSocialAccount;
use App\Domain\Social\Publishing\MetaPublisher;
use App\Domain\Social\Services\MarketingCampaignPostVersionMediaResolver;

class MetaPreflightService
{
    public function __construct(
        private MetaPublisher $publisher,
        private SocialMediaPublicUrlService $mediaUrlService,
        private MarketingCampaignPostVersionMediaResolver $mediaResolver
    ) {}

    public function runPreflight(MarketingCampaignPost $post, ClientSocialAccount $account): PreflightResult
    {
        $result = new PreflightResult(true);

        $platform = $account->platform->value;

        // 1. Validazione Account e Token
        $hasCapabilities = $this->publisher->verifyAccountCapabilities($account);
        $result->addCheck('account_capabilities', $hasCapabilities, $hasCapabilities ? null : 'Account non configurato per la pubblicazione API o token mancante/scaduto.');

        if (!$hasCapabilities) {
            // Se l'account non è configurato non possiamo fare controlli aggiuntivi sulle API
            return $result;
        }

        // 2. Controllo Media
        try {
            $mediaItems = $this->mediaResolver->resolveForPost($post)->mediaItems;
        } catch (\App\Domain\Social\Exceptions\MarketingCampaignPostMediaResolutionException $e) {
            $result->addCheck('media_resolution', false, "Impossibile risolvere i media per il post: " . $e->getMessage());
            return $result;
        }
        
        if ($platform === 'instagram') {
            $hasMedia = $mediaItems->count() > 0;
            $result->addCheck('instagram_media_present', $hasMedia, $hasMedia ? null : 'Instagram richiede almeno un file multimediale (Immagine o Video).');

            if ($hasMedia) {
                // Carousel Limit: Misto immagini e video
                if ($mediaItems->count() > 1) {
                    $result->addCheck('carousel_count_limit', $mediaItems->count() <= 10, $mediaItems->count() <= 10 ? null : 'Instagram supporta un massimo di 10 media in un carousel.');

                    $hasVideo = $mediaItems->contains(fn($m) => strtolower($m->media_type ?? '') === 'video' || in_array(strtolower(pathinfo($m->path ?? '', PATHINFO_EXTENSION)), ['mp4', 'mov', 'webm']));
                    $hasPhoto = $mediaItems->contains(fn($m) => strtolower($m->media_type ?? '') !== 'video' && !in_array(strtolower(pathinfo($m->path ?? '', PATHINFO_EXTENSION)), ['mp4', 'mov', 'webm']));

                    if ($hasVideo && $hasPhoto || ($hasVideo && $mediaItems->count() > 1)) {
                        $result->addCheck('carousel_mixed_format', false, 'Supporto carousel immagini-only per ora. Non sono ammessi formati misti o video multipli in questa versione.');
                    }
                }

                // Check Media Specs (Immagini: JPEG, max 8MB) (Video: max limit)
                foreach ($mediaItems as $media) {
                    $ext = strtolower(pathinfo($media->path ?? '', PATHINFO_EXTENSION));
                    $isVideo = strtolower($media->media_type ?? '') === 'video' || in_array($ext, ['mp4', 'mov', 'webm']);

                    if (!$isVideo) {
                        $isJpegOrPng = in_array($ext, ['jpg', 'jpeg', 'png']);
                        $result->addCheck("media_format_{$media->id}", $isJpegOrPng, $isJpegOrPng ? null : "Il file '{$media->original_name}' ha un formato ($ext) non supportato per le immagini IG (richiesto JPG o PNG).");
                        
                        $sizeMB = $media->size / 1024 / 1024;
                        $isUnderLimit = $sizeMB <= 8; // Instagram photo limit is 8MB
                        $result->addCheck("media_size_{$media->id}", $isUnderLimit, $isUnderLimit ? null : "Il file '{$media->original_name}' supera il limite di 8 MB per le immagini Instagram.");
                    } else {
                        $sizeMB = $media->size / 1024 / 1024;
                        $isUnderVideoLimit = $sizeMB <= 100; // Realistic practical limit for short videos
                        $result->addCheck("media_video_size_{$media->id}", $isUnderVideoLimit, $isUnderVideoLimit ? null : "Il video '{$media->original_name}' supera il limite dimensionale supportato.");
                    }

                    // Check URL generabile/pubblico
                    try {
                        // Generate the URL without running full HEAD check just to see if it throws on internal/private network
                        // Actually we can perform a quick check, but it might be slow.
                        // For a quick preflight we assume generateUrl/ensureSecureHost is what we want.
                        $this->mediaUrlService->getValidatedPublicUrl($media);
                        $result->addCheck("media_url_{$media->id}", true);
                    } catch (\Exception $e) {
                        $result->addCheck("media_url_{$media->id}", false, "Errore risoluzione media '{$media->original_name}': " . $e->getMessage());
                    }
                }

                // Check Reel
                $contentTypeStr = $post->content_type instanceof \App\Enums\Social\MarketingCampaignPostType 
                    ? $post->content_type->value 
                    : $post->content_type;

                if ($contentTypeStr === 'reel') {
                    $hasOnlyVideo = $mediaItems->count() === 1 && (strtolower($mediaItems->first()->media_type ?? '') === 'video' || in_array(strtolower(pathinfo($mediaItems->first()->path ?? '', PATHINFO_EXTENSION)), ['mp4', 'mov', 'webm']));
                    $result->addCheck('reel_media_valid', $hasOnlyVideo, $hasOnlyVideo ? null : 'Un Reel richiede obbligatoriamente un (e un solo) file video.');
                }
            }
        } elseif ($platform === 'facebook') {
            // Facebook supporta post di solo testo, foto o video
            // Non c'è un blocco forte come su IG, ma controlliamo comunque se c'è un file multimediale che generi eccezioni URL
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
