<?php

namespace App\Console\Commands;

use App\Domain\Finance\Actions\SyncElectronicInvoiceTransmissionAction;
use App\Enums\Finance\ElectronicInvoiceTransmissionStatus;
use App\Models\ElectronicInvoiceTransmission;
use App\Services\Integrations\Aruba\ArubaConfiguration;
use App\Support\Monitoring\TracksSystemCommandRuns;
use Illuminate\Console\Command;
use Throwable;

class SyncElectronicInvoiceStatusesCommand extends Command
{
    use TracksSystemCommandRuns;

    protected $signature = 'invoices:sync-electronic-statuses {--limit=5}';

    protected $description = 'Aggiorna da Aruba gli invii di fatture elettroniche ancora in lavorazione';

    public function handle(
        ArubaConfiguration $configuration,
        SyncElectronicInvoiceTransmissionAction $action,
    ): int {
        return $this->runTracked($this->getName(), function () use ($configuration, $action): int {
            if (! $configuration->enabled() || ! $configuration->credentialsConfigured()) {
                $this->info('Collegamento Aruba non attivo: nessun aggiornamento necessario.');

                return self::SUCCESS;
            }

            $limit = min(5, max(1, (int) $this->option('limit')));
            $transmissions = ElectronicInvoiceTransmission::query()
                ->where('provider', 'aruba')
                ->where('environment', $configuration->environment())
                ->where('mode', 'live')
                ->whereNotNull('upload_filename')
                ->whereIn('status', [
                    ElectronicInvoiceTransmissionStatus::Processing->value,
                    ElectronicInvoiceTransmissionStatus::Uncertain->value,
                    ElectronicInvoiceTransmissionStatus::TakenCharge->value,
                    ElectronicInvoiceTransmissionStatus::Sent->value,
                ])
                ->oldest('last_status_at')
                ->oldest('id')
                ->limit($limit)
                ->get();

            $updated = 0;
            $failed = 0;

            foreach ($transmissions as $transmission) {
                try {
                    $action->execute($transmission);
                    $updated++;
                } catch (Throwable $exception) {
                    report($exception);
                    $failed++;
                    $this->warn("Invio {$transmission->id}: aggiornamento non completato.");
                }
            }

            $this->info("Aggiornamenti completati: {$updated}. Non completati: {$failed}.");

            return $failed > 0 ? self::FAILURE : self::SUCCESS;
        }, [
            'maximum_transmissions' => min(5, max(1, (int) $this->option('limit'))),
        ]);
    }
}
