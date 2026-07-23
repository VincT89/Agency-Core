<?php

namespace App\Domain\Social\Services;

use App\Domain\Social\DTOs\MarketingCampaignPostMediaResolution;
use App\Domain\Social\Enums\MarketingCampaignPostMediaResolutionSource;
use App\Domain\Social\Exceptions\MarketingCampaignPostMediaResolutionException;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MarketingCampaignPostVersionMediaResolver
{
    /**
     * @deprecated Use resolveForVersion() or resolveForPost() instead.
     */
    public function resolveMediaItems(MarketingCampaignPostVersion $version): Collection
    {
        return $this->resolveForVersion($version)->mediaItems;
    }

    public function resolveForPost(MarketingCampaignPost $post): MarketingCampaignPostMediaResolution
    {
        $post->loadMissing('currentVersion');

        if ($post->current_version_id) {
            $currentVersion = $post->currentVersion;
            
            if (!$currentVersion || $currentVersion->marketing_campaign_post_id !== $post->id) {
                throw MarketingCampaignPostMediaResolutionException::forMissingCurrentVersion($post->id);
            }

            return $this->resolveForVersion($currentVersion);
        }

        return new MarketingCampaignPostMediaResolution(
            mediaItems: $post->orderedMediaItems,
            source: MarketingCampaignPostMediaResolutionSource::DRAFT_POST,
            versionId: null,
            usesLegacyFallback: false
        );
    }

    public function resolveForVersion(MarketingCampaignPostVersion $version): MarketingCampaignPostMediaResolution
    {
        $version->loadMissing(['mediaItems', 'post.mediaItems']);
        $post = $version->post;

        // Passo 1 — Pivot
        if ($version->mediaItems->isNotEmpty()) {
            foreach ($version->mediaItems as $media) {
                if ($media->marketing_campaign_post_id !== $version->marketing_campaign_post_id) {
                    throw MarketingCampaignPostMediaResolutionException::forForeignMedia(
                        $media->id, 
                        $media->marketing_campaign_post_id, 
                        $version->marketing_campaign_post_id
                    );
                }
            }

            return new MarketingCampaignPostMediaResolution(
                mediaItems: $version->mediaItems,
                source: MarketingCampaignPostMediaResolutionSource::VERSION_PIVOT,
                versionId: $version->id,
                usesLegacyFallback: false
            );
        }

        // Passo 2 — Riferimenti legacy della versione
        $legacyMediaIds = [];
        $resolvedMedia = collect();
        $legacyReferences = collect();

        if (is_array($version->image_urls)) {
            $legacyReferences = $legacyReferences->merge($version->image_urls);
        }
        if ($version->image_url) {
            $legacyReferences->push($version->image_url);
        }
        if ($version->image_path) {
            $legacyReferences->push($version->image_path);
        }

        $legacyReferences = $legacyReferences->unique()->values();

        if ($legacyReferences->isNotEmpty()) {
            foreach ($legacyReferences as $reference) {
                $media = $this->resolveLegacyReferenceToMedia($reference, $post);
                
                if (!$media) {
                    throw MarketingCampaignPostMediaResolutionException::forMissingLegacyReference((string)$reference, $version->id);
                }

                if (!in_array($media->id, $legacyMediaIds)) {
                    $legacyMediaIds[] = $media->id;
                    $resolvedMedia->push($media);
                }
            }

            $this->logLegacyFallback($version->id, $post->id, MarketingCampaignPostMediaResolutionSource::VERSION_LEGACY, $legacyMediaIds);

            return new MarketingCampaignPostMediaResolution(
                mediaItems: $resolvedMedia,
                source: MarketingCampaignPostMediaResolutionSource::VERSION_LEGACY,
                versionId: $version->id,
                usesLegacyFallback: true
            );
        }

        // Passo 3 — Campi legacy del post corrente
        if ($post->current_version_id === $version->id) {
            $media = $this->resolveLegacyPostFieldsToMedia($post);

            if ($media) {
                $legacyMediaIds = [$media->id];
                $resolvedMedia = collect([$media]);

                $this->logLegacyFallback($version->id, $post->id, MarketingCampaignPostMediaResolutionSource::CURRENT_POST_LEGACY, $legacyMediaIds);

                return new MarketingCampaignPostMediaResolution(
                    mediaItems: $resolvedMedia,
                    source: MarketingCampaignPostMediaResolutionSource::CURRENT_POST_LEGACY,
                    versionId: $version->id,
                    usesLegacyFallback: true
                );
            }
        }

        // Passo 4 — Errore conservativo
        throw MarketingCampaignPostMediaResolutionException::forUnresolvableVersion($version->id);
    }

    private function resolveLegacyReferenceToMedia(string $reference, MarketingCampaignPost $post)
    {
        $matches = [];
        $allMedia = $post->mediaItems;
        $reference = trim($reference);

        foreach ($allMedia as $media) {
            if ($this->mediaMatchesReference($media, $reference)) {
                $matches[] = $media;
            }
        }

        if (count($matches) === 0) {
            return null;
        }

        if (count($matches) > 1) {
            throw MarketingCampaignPostMediaResolutionException::forAmbiguousLegacyReference($reference, $post->current_version_id ?? 0);
        }

        return $matches[0];
    }

    private function mediaMatchesReference($media, string $reference): bool
    {
        $reference = rtrim($reference, '/');
        $referenceWithoutDownload = preg_replace('/\/download$/', '', $reference);

        if ($media->nextcloud_share_url) {
            $ncUrl = rtrim($media->nextcloud_share_url, '/');
            $ncUrlWithoutDownload = preg_replace('/\/download$/', '', $ncUrl);
            if ($ncUrlWithoutDownload === $referenceWithoutDownload) {
                return true;
            }
        }

        if ($media->disk && $media->path) {
            if ($reference === $media->path) {
                return true;
            }
            if (Str::endsWith($reference, $media->path)) {
                return true;
            }
            
            try {
                $storageUrl = Storage::disk($media->disk)->url($media->path);
                if ($reference === $storageUrl || rtrim($reference, '/') === rtrim($storageUrl, '/')) {
                    return true;
                }
            } catch (\Exception $e) {
                // Ignore missing disk or misconfiguration
            }
            
            if (Str::contains($reference, '/delivery/' . $media->id)) {
                return true;
            }
        }
        
        return false;
    }

    private function resolveLegacyPostFieldsToMedia(MarketingCampaignPost $post)
    {
        $matches = [];
        $allMedia = $post->mediaItems;
        
        $reference = $post->nextcloud_share_url ?: $post->media_url ?: $post->media_path;
        
        if (!$reference) {
            return null;
        }

        foreach ($allMedia as $media) {
            if ($this->mediaMatchesReference($media, $reference)) {
                $matches[] = $media;
            }
        }
        
        if (count($matches) !== 1) {
            return null;
        }
        
        $matchedMedia = $matches[0];
        
        if ($allMedia->count() > 1) {
            return null;
        }

        return $matchedMedia;
    }

    private function logLegacyFallback(int $versionId, int $postId, MarketingCampaignPostMediaResolutionSource $source, array $mediaIds): void
    {
        Log::notice('social.version_media.legacy_resolution', [
            'marketing_campaign_post_id' => $postId,
            'marketing_campaign_post_version_id' => $versionId,
            'resolution_source' => $source->value,
            'resolved_media_ids' => $mediaIds,
        ]);
    }
}
