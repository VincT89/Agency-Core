<?php

namespace App\Domain\Social\Services;

use App\Domain\Social\DTOs\VersionMediaBackfillAssessment;
use App\Domain\Social\Enums\VersionMediaBackfillClassification;
use App\Models\MarketingCampaignPostMedia;
use App\Models\MarketingCampaignPostVersion;
use Illuminate\Support\Collection;

final class VersionMediaPivotBackfillAssessor
{
    public function __construct(
        private readonly LegacyMarketingCampaignPostMediaMatcher $matcher
    ) {}

    public function assess(MarketingCampaignPostVersion $version): VersionMediaBackfillAssessment
    {
        $version->loadMissing(['mediaItems', 'post.mediaItems']);
        $post = $version->post;

        if ($version->mediaItems->isNotEmpty()) {
            $foreignMedia = $version->mediaItems->first(
                fn (MarketingCampaignPostMedia $media): bool => $media->marketing_campaign_post_id !== $version->marketing_campaign_post_id
            );

            if ($foreignMedia) {
                return new VersionMediaBackfillAssessment(
                    versionId: $version->id,
                    postId: $version->marketing_campaign_post_id,
                    classification: VersionMediaBackfillClassification::ForeignMedia,
                    mediaIds: $version->mediaItems->pluck('id')->map(fn ($id) => (int) $id)->all(),
                    reason: "media {$foreignMedia->id} belongs to post {$foreignMedia->marketing_campaign_post_id}"
                );
            }

            return new VersionMediaBackfillAssessment(
                versionId: $version->id,
                postId: $version->marketing_campaign_post_id,
                classification: VersionMediaBackfillClassification::AlreadyPopulated,
                mediaIds: $version->mediaItems->pluck('id')->map(fn ($id) => (int) $id)->all()
            );
        }

        $references = $this->matcher->referencesForVersion($version);
        $candidateMedia = $post->mediaItems;

        if ($references->isEmpty() && $post->current_version_id === $version->id) {
            $postReferences = $this->matcher->referencesForPost($post);

            // Post-level fields only identify the complete historical set when the
            // post owns exactly one media item. Anything broader is unsafe.
            if ($candidateMedia->count() === 1) {
                $references = $postReferences;
            } elseif ($postReferences->isNotEmpty()) {
                return new VersionMediaBackfillAssessment(
                    versionId: $version->id,
                    postId: $version->marketing_campaign_post_id,
                    classification: VersionMediaBackfillClassification::Ambiguous,
                    reason: 'post-level legacy fields cannot identify a complete multi-media set'
                );
            }
        }

        if ($references->isEmpty()) {
            return new VersionMediaBackfillAssessment(
                versionId: $version->id,
                postId: $version->marketing_campaign_post_id,
                classification: VersionMediaBackfillClassification::Unresolvable,
                reason: 'no legacy media references are available'
            );
        }

        $resolved = collect();

        foreach ($references as $reference) {
            $matches = $this->matcher->matchingMedia($reference, $candidateMedia);

            if ($matches->count() > 1) {
                return new VersionMediaBackfillAssessment(
                    versionId: $version->id,
                    postId: $version->marketing_campaign_post_id,
                    classification: VersionMediaBackfillClassification::Ambiguous,
                    reason: "legacy reference '{$reference}' matches multiple media"
                );
            }

            if ($matches->isEmpty()) {
                return new VersionMediaBackfillAssessment(
                    versionId: $version->id,
                    postId: $version->marketing_campaign_post_id,
                    classification: VersionMediaBackfillClassification::Unresolvable,
                    reason: "legacy reference '{$reference}' does not match a media owned by the post"
                );
            }

            $resolved->push($matches->first());
        }

        /** @var Collection<int, MarketingCampaignPostMedia> $resolved */
        $resolved = $resolved->unique('id')->values();

        if ($resolved->isEmpty()) {
            return new VersionMediaBackfillAssessment(
                versionId: $version->id,
                postId: $version->marketing_campaign_post_id,
                classification: VersionMediaBackfillClassification::Unresolvable,
                reason: 'legacy references resolved to an empty media set'
            );
        }

        return new VersionMediaBackfillAssessment(
            versionId: $version->id,
            postId: $version->marketing_campaign_post_id,
            classification: VersionMediaBackfillClassification::DeterministicallyResolvable,
            mediaIds: $resolved->pluck('id')->map(fn ($id) => (int) $id)->all()
        );
    }
}
