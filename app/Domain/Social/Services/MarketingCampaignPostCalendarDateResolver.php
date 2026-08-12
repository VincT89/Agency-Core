<?php

namespace App\Domain\Social\Services;

use App\Models\MarketingCampaignPost;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class MarketingCampaignPostCalendarDateResolver
{
    public function resolve(MarketingCampaignPost $post): ?CarbonInterface
    {
        $publishedAt = $this->latestSuccessfulPublicationDate($post)
            ?? $post->published_at;

        if ($publishedAt) {
            return Carbon::instance($publishedAt)->copy();
        }

        if (! $post->scheduled_date) {
            return null;
        }

        $date = $post->scheduled_date->copy()->startOfDay();

        if (! $post->scheduled_time) {
            return $date->setTime(12, 0);
        }

        $time = Carbon::parse($post->scheduled_time);

        return $date->setTime(
            $time->hour,
            $time->minute,
            $time->second,
        );
    }

    private function latestSuccessfulPublicationDate(MarketingCampaignPost $post): ?CarbonInterface
    {
        $publications = $post->relationLoaded('successfulPublications')
            ? $post->successfulPublications
            : $post->successfulPublications()->get();

        return $publications
            ->sortByDesc(fn ($publication) => $publication->published_at?->getTimestamp() ?? 0)
            ->first()
            ?->published_at;
    }
}
