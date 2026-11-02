<?php

namespace App\Console\Commands;

use App\Domain\Availability\Actions\RecordPresence;
use Illuminate\Console\Command;

class ExpirePresenceSessions extends Command
{
    protected $signature = 'presence:expire';
    protected $description = 'Chiude le presenze scollegate e sospende i conteggi alla fine della fascia.';

    public function handle(RecordPresence $record): int
    {
        $this->info('Presenze chiuse: '.$record->expire());

        return self::SUCCESS;
    }
}
