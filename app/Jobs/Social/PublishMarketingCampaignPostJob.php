<?php

namespace App\Jobs\Social;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\MarketingCampaignPostPublication;
use App\Models\MarketingCampaignPost;
use Illuminate\Support\Facades\Log;

use Illuminate\Contracts\Queue\ShouldBeUnique;

class PublishMarketingCampaignPostJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public MarketingCampaignPost $post;
    public string $platform;
    public ?string $correlationId;
    public ?MarketingCampaignPostPublication $retryPublication;

    public function __construct(
        MarketingCampaignPost $post,
        string $platform,
        ?string $correlationId = null,
        ?MarketingCampaignPostPublication $retryPublication = null
    ) {
        $this->post = $post;
        $this->platform = $platform;
        $this->correlationId = $correlationId;
        $this->retryPublication = $retryPublication;
    }

    public function uniqueId(): string
    {
        return $this->post->id . '-' . $this->platform;
    }

    public function handle(): void
    {
        Log::info("Legacy PublishMarketingCampaignPostJob invocato e intercettato dal drain shim.", [
            'post_id' => $this->post->id ?? null,
            'retry_publication_id' => $this->retryPublication->id ?? null,
        ]);

        if ($this->retryPublication) {
            // Ricarichiamo fresco per assicurarci di avere lo stato aggiornato
            $publication = MarketingCampaignPostPublication::find($this->retryPublication->id);
            
            if ($publication && in_array($publication->status->value, ['pending', 'publishing']) && $publication->snapshot_schema_version === null) {
                $publication->update([
                    'status' => 'abandoned',
                    'error_message' => 'Job drain (legacy): intercettato job pendente di vecchio tipo e convertito in abandoned.',
                ]);
            }
        }
    }
}

