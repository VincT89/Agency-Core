<?php

namespace App\Console\Commands\Social;

use App\Domain\Social\Enums\VersionMediaBackfillClassification;
use App\Domain\Social\Services\VersionMediaPivotBackfillAssessor;
use App\Models\MarketingCampaignPostVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillPostMediaPivot extends Command
{
    protected $signature = 'social:backfill-post-media-pivot
        {--apply : Applica esclusivamente le associazioni deterministiche}
        {--post-id=* : Limita il controllo a uno o più post}
        {--version-id=* : Limita il controllo a una o più versioni}
        {--chunk=200 : Numero di versioni elaborate per blocco}';

    protected $description = 'Classifica e, con --apply, ricostruisce in sicurezza le pivot media delle versioni legacy';

    public function handle(VersionMediaPivotBackfillAssessor $assessor): int
    {
        $apply = (bool) $this->option('apply');
        $chunkSize = max(1, min(1000, (int) $this->option('chunk')));
        $counts = array_fill_keys(
            array_map(
                fn (VersionMediaBackfillClassification $case): string => $case->value,
                VersionMediaBackfillClassification::cases()
            ),
            0
        );
        $attachedMedia = 0;

        $this->info(
            $apply
                ? 'Backfill pivot media: modalità APPLY'
                : 'Backfill pivot media: modalità DRY-RUN. Nessuna modifica verrà salvata.'
        );

        $query = MarketingCampaignPostVersion::query()->orderBy('id');
        $postIds = $this->positiveIds((array) $this->option('post-id'));
        $versionIds = $this->positiveIds((array) $this->option('version-id'));

        if ($postIds !== []) {
            $query->whereIn('marketing_campaign_post_id', $postIds);
        }

        if ($versionIds !== []) {
            $query->whereIn('id', $versionIds);
        }

        $query->chunkById($chunkSize, function ($versions) use (
            $assessor,
            $apply,
            &$counts,
            &$attachedMedia
        ): void {
            foreach ($versions as $version) {
                $assessment = $assessor->assess($version);
                $counts[$assessment->classification->value]++;

                if (! $apply || ! $assessment->isSafeToApply()) {
                    $this->reportAssessment($assessment);

                    continue;
                }

                $appliedAssessment = DB::transaction(function () use (
                    $version,
                    $assessor
                ) {
                    $lockedVersion = MarketingCampaignPostVersion::query()
                        ->whereKey($version->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $lockedVersion->post()
                        ->lockForUpdate()
                        ->firstOrFail();

                    $lockedVersion->unsetRelation('mediaItems');
                    $lockedVersion->unsetRelation('post');
                    $freshAssessment = $assessor->assess($lockedVersion);

                    if (! $freshAssessment->isSafeToApply()) {
                        return $freshAssessment;
                    }

                    $lockedVersion->mediaItems()->attach(
                        $freshAssessment->pivotPayload()
                    );

                    return $freshAssessment;
                });

                if ($appliedAssessment->isSafeToApply()) {
                    $attachedMedia += count($appliedAssessment->mediaIds);
                    $this->line(
                        "version={$appliedAssessment->versionId} applied media=".
                        implode(',', $appliedAssessment->mediaIds)
                    );
                } else {
                    $counts[$assessment->classification->value]--;
                    $counts[$appliedAssessment->classification->value]++;
                    $this->reportAssessment($appliedAssessment);
                }
            }
        });

        $this->newLine();
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
        $this->line("Media associati in questa esecuzione: {$attachedMedia}");

        $attentionCount =
            $counts[VersionMediaBackfillClassification::Ambiguous->value]
            + $counts[VersionMediaBackfillClassification::Unresolvable->value]
            + $counts[VersionMediaBackfillClassification::ForeignMedia->value];

        if ($attentionCount > 0) {
            $this->error(
                "{$attentionCount} versioni richiedono verifica manuale; nessuna di esse è stata modificata."
            );

            return self::FAILURE;
        }

        if (! $apply &&
            $counts[VersionMediaBackfillClassification::DeterministicallyResolvable->value] > 0) {
            $this->warn('Sono presenti associazioni deterministiche. Rieseguire con --apply per salvarle.');
        }

        return self::SUCCESS;
    }

    /**
     * @param array<mixed> $values
     * @return list<int>
     */
    private function positiveIds(array $values): array
    {
        return collect($values)
            ->filter(fn (mixed $value): bool => ctype_digit((string) $value) && (int) $value > 0)
            ->map(fn (mixed $value): int => (int) $value)
            ->unique()
            ->values()
            ->all();
    }

    private function reportAssessment(
        \App\Domain\Social\DTOs\VersionMediaBackfillAssessment $assessment
    ): void {
        if (! $assessment->classification->requiresAttention()) {
            return;
        }

        $this->warn(sprintf(
            'version=%d post=%d classification=%s reason=%s',
            $assessment->versionId,
            $assessment->postId,
            $assessment->classification->value,
            $assessment->reason ?? 'n/a'
        ));
    }
}
