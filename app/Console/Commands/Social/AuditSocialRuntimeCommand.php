<?php

namespace App\Console\Commands\Social;

use App\Enums\Social\PublicationStatus;
use App\Models\MarketingCampaignPostPublication;
use App\Support\Monitoring\TracksSystemCommandRuns;
use Illuminate\Console\Command;

class AuditSocialRuntimeCommand extends Command
{
    use TracksSystemCommandRuns;

    protected $signature = 'social:audit-runtime
        {--hours=24 : Finestra temporale dei fallimenti}
        {--fail-on-actionable : Restituisce errore se esistono revisioni manuali o publication stale}';

    protected $description = 'Riepiloga coda, fallimenti, revisioni manuali e publication stale';

    public function handle(): int
    {
        $hours = max(1, min(24 * 30, (int) $this->option('hours')));

        return $this->runTracked($this->getName(), function () use ($hours): int {
            $counts = MarketingCampaignPostPublication::query()
                ->selectRaw('platform, status, COUNT(*) AS aggregate')
                ->groupBy('platform', 'status')
                ->orderBy('platform')
                ->orderBy('status')
                ->get();

            $this->table(
                ['Piattaforma', 'Stato', 'Totale'],
                $counts->map(fn ($row): array => [
                    $row->platform->value,
                    $row->status->value,
                    (int) $row->aggregate,
                ])->all()
            );

            $manualReview = MarketingCampaignPostPublication::query()
                ->where('status', PublicationStatus::NeedsManualReview)
                ->count();
            $stale = MarketingCampaignPostPublication::query()
                ->whereIn('status', [
                    PublicationStatus::Pending,
                    PublicationStatus::Publishing,
                ])
                ->whereNotNull('stale_deadline_at')
                ->where('stale_deadline_at', '<', now())
                ->count();
            $recentFailures = MarketingCampaignPostPublication::query()
                ->where('status', PublicationStatus::Failed)
                ->where('updated_at', '>=', now()->subHours($hours))
                ->count();

            $this->line("Revisioni manuali: {$manualReview}");
            $this->line("Publication stale: {$stale}");
            $this->line("Fallimenti nelle ultime {$hours} ore: {$recentFailures}");

            if (
                (bool) $this->option('fail-on-actionable')
                && ($manualReview > 0 || $stale > 0)
            ) {
                $this->error('Sono presenti publication che richiedono un intervento.');

                return self::FAILURE;
            }

            return self::SUCCESS;
        }, ['hours' => $hours]);
    }
}
