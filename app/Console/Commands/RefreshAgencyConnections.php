<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AgencySocialConnection;
use App\Domain\Social\Actions\RefreshAgencyConnectionAction;

class RefreshAgencyConnections extends Command
{
    protected $signature = 'social:refresh-agency-connections';
    protected $description = 'Rinnova i long-lived token delle connessioni agency Meta in scadenza';

    public function handle(RefreshAgencyConnectionAction $action): int
    {
        $connections = AgencySocialConnection::where('provider', 'facebook')
            ->where('requires_reauth', false)
            ->whereNotNull('access_token')
            ->where(function ($q) {
                $q->whereNull('token_expires_at')
                  ->orWhere('token_expires_at', '<=', now()->addDays(10));
            })
            ->get();

        foreach ($connections as $connection) {
            $ok = $action->execute($connection);
            $this->info("Connection {$connection->id}: " . ($ok ? 'rinnovata' : 'fallita (vedi log)'));
        }
        
        return self::SUCCESS;
    }
}
