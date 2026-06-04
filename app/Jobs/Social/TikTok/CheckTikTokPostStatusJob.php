<?php

namespace App\Jobs\Social\TikTok;

use App\Models\MarketingCampaignPostPublication;
use App\Domain\Social\TikTok\TikTokContentPostingService;
use App\Domain\Social\TikTok\TikTokPostStatusService;
use App\Enums\Social\PublicationStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\RequestException;
use App\Enums\Social\SocialApiStatus;

class CheckTikTokPostStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 10;

    public function __construct(
        private readonly int $publicationId
    ) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->publicationId))->expireAfter(900)];
    }

    public function handle(
        TikTokContentPostingService $contentService,
        TikTokPostStatusService $statusService
    ): void {
        $pub = MarketingCampaignPostPublication::find($this->publicationId);

        if (!$pub || $pub->status !== PublicationStatus::Publishing) {
            return;
        }

        // Il external_container_id contiene il publish_id asincrono restituito da TikTok
        if (empty($pub->external_container_id)) {
            $pub->update([
                'status' => PublicationStatus::Failed,
                'error_message' => 'Nessun publish_id di TikTok trovato per fare polling.'
            ]);
            return;
        }

        $account = $pub->socialAccount;
        if (!$account || empty($account->access_token)) {
            $pub->update([
                'status' => PublicationStatus::Failed,
                'error_message' => 'Account non trovato o token mancante per il polling TikTok.'
            ]);
            return;
        }

        try {
            $pub->increment('poll_count');
            $rawStatus = $contentService->getPostStatus($account->access_token, $pub->external_container_id);
            $newStatus = $statusService->mapStatus($rawStatus);

            if ($newStatus === PublicationStatus::Publishing) {
                // Se stiamo per esaurire i tentativi, non lo marchiamo fallito, ma manual review
                if ($this->attempts() >= $this->tries - 1) {
                    $pub->update([
                        'status' => PublicationStatus::Failed,
                        'error_message' => 'Timeout: TikTok elaborazione bloccata o troppo lenta (max tentativi raggiunti).'
                    ]);
                    app(\App\Domain\Social\Actions\SyncMarketingCampaignPostPublicationStatusAction::class)->execute($pub->post);
                    return;
                }

                // Ancora in elaborazione. Progressive backoff.
                $delay = min($this->attempts() * 60, 600); // Da 1 a 10 minuti
                $this->release($delay);
                return;
            }

            // Status finale raggiunto (Published, Failed, Cancelled)
            $pub->update([
                'status' => $newStatus,
                'published_at' => $newStatus === PublicationStatus::Published ? now() : null,
                'delivery_state' => $newStatus === PublicationStatus::Published ? 'delivered_to_tiktok' : null,
                'error_message' => $newStatus === PublicationStatus::Failed ? 'La pubblicazione è fallita asincronamente su TikTok.' : null,
            ]);
            
            app(\App\Domain\Social\Actions\SyncMarketingCampaignPostPublicationStatusAction::class)->execute($pub->post);

            Log::info("TikTok polling concluso con successo", [
                'publication_id' => $pub->id,
                'new_status' => $newStatus->value,
                'raw_tiktok_status' => $rawStatus
            ]);

        } catch (\Exception $e) {
            Log::error("Errore CheckTikTokPostStatusJob durante il polling", [
                'publication_id' => $pub->id,
                'error' => $e->getMessage()
            ]);
            
            if ($e instanceof RequestException) {
                $status = $e->response->status();
                if ($status === 401 || $status === 403) {
                    // Token scaduto o non autorizzato
                    $account->update([
                        'requires_reauth' => true,
                        'api_status' => SocialApiStatus::Error
                    ]);
                    $pub->update([
                        'status' => PublicationStatus::Failed,
                        'error_message' => "Autorizzazione fallita ($status). Token scaduto o permessi insufficienti."
                    ]);
                    app(\App\Domain\Social\Actions\SyncMarketingCampaignPostPublicationStatusAction::class)->execute($pub->post);
                    return;
                }
                
                if ($status === 429) {
                    // Rate limit: backoff lungo
                    $this->release(300);
                    return;
                }
                
                if ($status >= 500) {
                    // Errore server TikTok: exponential backoff
                    $delay = min($this->attempts() * 60, 600);
                    $this->release($delay);
                    return;
                }
                
                if ($status >= 400 && $status < 500) {
                    // Errore permanente del client (es. payload non valido)
                    $pub->update([
                        'status' => PublicationStatus::Failed,
                        'error_message' => "Errore API permanente ($status): " . $e->getMessage()
                    ]);
                    app(\App\Domain\Social\Actions\SyncMarketingCampaignPostPublicationStatusAction::class)->execute($pub->post);
                    return;
                }
            }

            // Fallimento network/timeout (transient puro)
            $this->release(60);
        }
    }
}
