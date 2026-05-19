<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ClientSocialAccount;
use Illuminate\Support\Facades\Log;

class SyncSocialAccountsStatus extends Command
{
    protected $signature = 'social:sync-accounts';
    protected $description = 'Verifica lo stato degli account social connessi e aggiorna i booleani/capability';

    public function handle()
    {
        $this->info("Sincronizzazione account social in corso...");

        $accounts = ClientSocialAccount::whereNotNull('access_token')->get();
        $synced = 0;

        foreach ($accounts as $account) {
            try {
                $account->verifyPublishingReadiness();
                $synced++;
            } catch (\Exception $e) {
                Log::error("Errore sync account social", [
                    'account_id' => $account->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info("Sincronizzati {$synced} account.");
    }
}
