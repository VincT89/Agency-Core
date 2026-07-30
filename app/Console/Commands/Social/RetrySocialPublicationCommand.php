<?php

namespace App\Console\Commands\Social;

use App\Domain\Social\Actions\RetryMarketingCampaignPostPublicationAction;
use App\Jobs\Social\ExecuteMarketingCampaignPostPublicationJob;
use App\Models\MarketingCampaignPostPublication;
use App\Support\Monitoring\TracksSystemCommandRuns;
use Illuminate\Console\Command;

class RetrySocialPublicationCommand extends Command
{
    use TracksSystemCommandRuns;

    protected $signature = 'social:retry-publication
        {publication_id : ID della publication fallita o in revisione manuale}
        {--no-dispatch : Crea il tentativo senza accodarne l’esecuzione}';

    protected $description = 'Crea un retry immutabile di una publication e lo accoda';

    public function handle(
        RetryMarketingCampaignPostPublicationAction $retryAction
    ): int {
        $publicationId = (int) $this->argument('publication_id');

        return $this->runTracked(
            $this->getName(),
            function () use ($publicationId, $retryAction): int {
                $publication = MarketingCampaignPostPublication::query()
                    ->findOrFail($publicationId);
                $retry = $retryAction->execute($publication);

                if (! (bool) $this->option('no-dispatch')) {
                    ExecuteMarketingCampaignPostPublicationJob::dispatch($retry->id);
                }

                $this->info(
                    "Retry creato: publication={$retry->id} attempt={$retry->attempt_count}"
                );

                return self::SUCCESS;
            },
            ['publication_id' => $publicationId]
        );
    }
}
