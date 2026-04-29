<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use Illuminate\Console\Command;

class BackfillTicketCodes extends Command
{

    protected $signature = 'app:backfill-ticket-codes';


    protected $description = 'Retroattivamente genera i codici Ticket per tutti i ticket esistenti sprovvisti di codice.';


    public function handle()
    {
        $tickets = Ticket::withoutGlobalScope(\App\Models\Scopes\ProjectSupremacyScope::class)
            ->where(function($q) {
                $q->whereNull('code')->orWhere('code', '');
            })->get();

        if ($tickets->isEmpty()) {
            $this->info('Nessun ticket sprovvisto di codice trovato. Tutti i ticket sono allineati.');
            return;
        }

        $this->info("Trovati {$tickets->count()} ticket da processare.");

        $bar = $this->output->createProgressBar(count($tickets));

        $bar->start();

        foreach ($tickets as $ticket) {
            $year = $ticket->created_at ? $ticket->created_at->format('Y') : date('Y');
            $code = sprintf('TCK-%s-%06d', $year, $ticket->id);
            $ticket->updateQuietly(['code' => $code]);
            
            $bar->advance();
        }

        $bar->finish();
        
        $this->newLine(2);
        $this->info('Backfill codici completato con successo.');
    }
}
