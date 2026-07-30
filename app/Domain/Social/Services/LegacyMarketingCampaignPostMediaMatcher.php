<?php

namespace App\Domain\Social\Services;

use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostMedia;
use App\Models\MarketingCampaignPostVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

final class LegacyMarketingCampaignPostMediaMatcher
{
    /**
     * @return Collection<int, string>
     */
    public function referencesForVersion(MarketingCampaignPostVersion $version): Collection
    {
        $references = collect();

        if (is_array($version->image_urls)) {
            $references = $references->merge($version->image_urls);
        }

        if (is_string($version->image_url) && trim($version->image_url) !== '') {
            $references->push($version->image_url);
        }

        if (is_string($version->image_path) && trim($version->image_path) !== '') {
            $references->push($version->image_path);
        }

        return $references
            ->filter(fn (mixed $reference): bool => is_string($reference) && trim($reference) !== '')
            ->map(fn (string $reference): string => trim($reference))
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    public function referencesForPost(MarketingCampaignPost $post): Collection
    {
        return collect([
            $post->getRawOriginal('nextcloud_share_url'),
            $post->getRawOriginal('media_path'),
        ])
            ->filter(fn (mixed $reference): bool => is_string($reference) && trim($reference) !== '')
            ->map(fn (string $reference): string => trim($reference))
            ->unique()
            ->values();
    }

    /**
     * @param  Collection<int, MarketingCampaignPostMedia>  $mediaItems
     * @return Collection<int, MarketingCampaignPostMedia>
     */
    public function matchingMedia(string $reference, Collection $mediaItems): Collection
    {
        return $mediaItems
            ->filter(
                fn (MarketingCampaignPostMedia $media): bool => $this->mediaMatchesReference(
                    $media,
                    $reference
                )
            )
            ->values();
    }

    public function mediaMatchesReference(
        MarketingCampaignPostMedia $media,
        string $reference
    ): bool {
        $normalizedReferencePath = $this->normalizedReferencePath($reference);

        if ($normalizedReferencePath === '') {
            return false;
        }

        foreach ([
            $media->nextcloud_share_url,
            $media->nextcloud_path,
            $media->path,
            $media->url,
        ] as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            if ($this->normalizedReferencePath($candidate) === $normalizedReferencePath) {
                return true;
            }
        }

        if (filled($media->disk) && filled($media->path)) {
            try {
                $storageUrl = Storage::disk($media->disk)->url($media->path);

                if ($this->normalizedReferencePath($storageUrl) === $normalizedReferencePath) {
                    return true;
                }
            } catch (\Throwable) {
                // An unavailable legacy disk cannot establish a deterministic match.
            }
        }

        $referencePath = rawurldecode((string) parse_url($reference, PHP_URL_PATH));

        return preg_match(
            '#/(?:delivery|media)/(\d+)$#',
            rtrim($referencePath, '/'),
            $matches
        ) === 1 && (int) $matches[1] === $media->id;
    }

    private function normalizedReferencePath(string $reference): string
    {
        $path = parse_url(trim($reference), PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            $path = trim($reference);
        }

        $path = rawurldecode($path);
        $path = preg_replace('#/download/?$#', '', $path) ?? $path;
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path) ?? $path;

        return ltrim($path, '/');
    }
}
