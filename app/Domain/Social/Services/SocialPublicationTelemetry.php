<?php

namespace App\Domain\Social\Services;

use App\Enums\Social\PublicationStatus;
use App\Models\MarketingCampaignPostPublication;
use Illuminate\Support\Facades\Log;

class SocialPublicationTelemetry
{
    public function record(
        MarketingCampaignPostPublication $publication,
        string $event,
        ?string $previousStatus = null
    ): void {
        $context = $this->context($publication, $event, $previousStatus);
        $level = in_array(
            $publication->status,
            [PublicationStatus::Failed, PublicationStatus::NeedsManualReview],
            true
        ) ? 'warning' : 'info';

        Log::channel('social-runtime')->{$level}($event, $context);
    }

    /**
     * @return array{
     *     correlation_id: ?string,
     *     post_id: ?int,
     *     version_id: ?int,
     *     publication_id: ?int,
     *     platform: ?string,
     *     account_id: ?int,
     *     snapshot_hash: ?string,
     *     attempt_count: int,
     *     event: string,
     *     previous_status: ?string,
     *     new_status: ?string,
     *     duration_ms: int,
     *     error_code: ?string
     * }
     */
    public function context(
        MarketingCampaignPostPublication $publication,
        string $event,
        ?string $previousStatus = null
    ): array {
        $startedAt = $publication->publishing_started_at
            ?? $publication->created_at;
        $durationMs = $startedAt
            ? max(0, (int) round($startedAt->diffInMilliseconds(now())))
            : 0;

        $platform = $publication->platform instanceof \BackedEnum
            ? $publication->platform->value
            : $publication->platform;
        $status = $publication->status instanceof \BackedEnum
            ? $publication->status->value
            : $publication->status;
        $failure = $publication->failure_classification instanceof \BackedEnum
            ? $publication->failure_classification->value
            : $publication->failure_classification;

        return [
            'correlation_id' => $publication->correlation_id,
            'post_id' => $publication->marketing_campaign_post_id,
            'version_id' => $publication->marketing_campaign_post_version_id,
            'publication_id' => $publication->getKey(),
            'platform' => $platform,
            'account_id' => $publication->client_social_account_id,
            'snapshot_hash' => $publication->snapshot_hash,
            'attempt_count' => (int) ($publication->attempt_count ?? 0),
            'event' => $event,
            'previous_status' => $previousStatus,
            'new_status' => $status,
            'duration_ms' => $durationMs,
            'error_code' => $failure ?: null,
        ];
    }
}
