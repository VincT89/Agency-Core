<?php

namespace App\Console\Commands\Social;

use App\Domain\Social\Enums\VersionMediaBackfillClassification;
use App\Domain\Social\Services\VersionMediaPivotBackfillAssessor;
use App\Models\MarketingCampaignPostVersion;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('social:audit-post-media-pivot {--chunk=200}')]
#[Description('Classifica l’integrità delle associazioni media di tutte le versioni')]
class AuditPostMediaPivot extends Command
{
    public function handle(VersionMediaPivotBackfillAssessor $assessor): int
    {
        $counts = array_fill_keys(
            array_map(
                fn (VersionMediaBackfillClassification $case): string => $case->value,
                VersionMediaBackfillClassification::cases()
            ),
            0
        );
        $chunkSize = max(1, min(1000, (int) $this->option('chunk')));

        MarketingCampaignPostVersion::query()
            ->orderBy('id')
            ->chunkById($chunkSize, function ($versions) use ($assessor, &$counts): void {
                foreach ($versions as $version) {
                    $assessment = $assessor->assess($version);
                    $counts[$assessment->classification->value]++;

                    if ($assessment->classification->requiresAttention()) {
                        $this->warn(sprintf(
                            'version=%d post=%d classification=%s reason=%s',
                            $assessment->versionId,
                            $assessment->postId,
                            $assessment->classification->value,
                            $assessment->reason ?? 'n/a'
                        ));
                    }
                }
            });

        $this->table(
            ['Classificazione', 'Versioni'],
            collect($counts)
                ->map(fn (int $count, string $classification): array => [
                    $classification,
                    $count,
                ])
                ->values()
                ->all()
        );

        $unsafe =
            $counts[VersionMediaBackfillClassification::Ambiguous->value]
            + $counts[VersionMediaBackfillClassification::Unresolvable->value]
            + $counts[VersionMediaBackfillClassification::ForeignMedia->value];
        $pending =
            $counts[VersionMediaBackfillClassification::DeterministicallyResolvable->value];

        if ($unsafe > 0) {
            $this->error("Audit non superato: {$unsafe} versioni richiedono verifica manuale.");

            return self::FAILURE;
        }

        if ($pending > 0) {
            $this->warn(
                "Audit incompleto: {$pending} versioni sono deterministiche ma non ancora popolate. ".
                'Eseguire social:backfill-post-media-pivot --apply.'
            );

            return self::FAILURE;
        }

        $this->info('Audit superato: tutte le pivot sono integre.');

        return self::SUCCESS;
    }
}
