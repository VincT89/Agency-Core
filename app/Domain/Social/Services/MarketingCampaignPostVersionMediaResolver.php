<?php

namespace App\Domain\Social\Services;

use App\Domain\Social\DTOs\MarketingCampaignPostMediaResolution;
use App\Domain\Social\Enums\MarketingCampaignPostMediaResolutionSource;
use App\Domain\Social\Exceptions\MarketingCampaignPostMediaResolutionException;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class MarketingCampaignPostVersionMediaResolver
{
    private readonly LegacyMarketingCampaignPostMediaMatcher $legacyMatcher;

    public function __construct(
        ?LegacyMarketingCampaignPostMediaMatcher $legacyMatcher = null
    ) {
        $this->legacyMatcher = $legacyMatcher
            ?? new LegacyMarketingCampaignPostMediaMatcher;
    }

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

            if (! $currentVersion || $currentVersion->marketing_campaign_post_id !== $post->id) {
                throw MarketingCampaignPostMediaResolutionException::forMissingCurrentVersion($post->id);
            }

            $currentVersion->setRelation('post', $post);

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
        $legacyReferences = $this->legacyMatcher->referencesForVersion($version);

        if ($legacyReferences->isNotEmpty()) {
            foreach ($legacyReferences as $reference) {
                $matches = $this->legacyMatcher->matchingMedia(
                    $reference,
                    $post->mediaItems
                );

                if ($matches->isEmpty()) {
                    throw MarketingCampaignPostMediaResolutionException::forMissingLegacyReference((string) $reference, $version->id);
                }

                if ($matches->count() > 1) {
                    throw MarketingCampaignPostMediaResolutionException::forAmbiguousLegacyReference(
                        (string) $reference,
                        $version->id
                    );
                }

                $media = $matches->first();

                if (! in_array($media->id, $legacyMediaIds)) {
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

    private function resolveLegacyPostFieldsToMedia(MarketingCampaignPost $post)
    {
        $allMedia = $post->mediaItems;

        $references = $this->legacyMatcher->referencesForPost($post);

        if ($references->isEmpty()) {
            return null;
        }

        if ($allMedia->count() !== 1) {
            return null;
        }

        $matchedMedia = $allMedia->first();

        foreach ($references as $reference) {
            if (! $this->legacyMatcher->mediaMatchesReference($matchedMedia, $reference)) {
                return null;
            }
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
