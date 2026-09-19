<?php

namespace App\Console\Commands;

use App\Domain\Finance\Actions\GenerateRecurringExpenses;
use App\Models\ExpenseRecurrence;
use Illuminate\Console\Command;

class GenerateRecurringExpensesCommand extends Command
{
    protected $signature = 'expenses:generate-recurring';

    protected $description = 'Genera le scadenze delle spese ricorrenti per i prossimi 24 mesi, senza duplicati.';

    public function handle(GenerateRecurringExpenses $generator): int
    {
        $count = 0;
        ExpenseRecurrence::where('active', true)->eachById(function ($recurrence) use ($generator, &$count) {
            $count += $generator->execute($recurrence);
        });
        $this->info("Scadenze generate: {$count}.");

        return self::SUCCESS;
    }
}
