<?php

namespace App\Observers;

use App\Domain\Social\Services\SocialPublicationTelemetry;
use App\Models\MarketingCampaignPostPublication;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class MarketingCampaignPostPublicationObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly SocialPublicationTelemetry $telemetry
    ) {}

    public function created(MarketingCampaignPostPublication $publication): void
    {
        $this->telemetry->record($publication, 'publication.created');
    }

    public function updated(MarketingCampaignPostPublication $publication): void
    {
        if (! $publication->wasChanged('status')) {
            return;
        }

        $previousStatus = $publication->getRawOriginal('status');
        $this->telemetry->record(
            $publication,
            'publication.status_changed',
            is_string($previousStatus) ? $previousStatus : null
        );
    }
}
