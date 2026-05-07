<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Client;
use App\Domain\Chatbot\Actions\SyncFullChatbotClientAction;
use App\Jobs\Chatbot\SyncChatbotClientDataJob;

class SyncChatbotTablesCommand extends Command
{
    protected $signature = 'chatbot:sync-tables {--client_id= : Sync a specific client ID} {--force : Force sync}';

    protected $description = 'Sync chatbot read-model tables with current operative data';

    public function handle(SyncFullChatbotClientAction $syncAction)
    {
        $clientId = $this->option('client_id');
        
        $query = Client::query()->where('status', 'active');
        if ($clientId) {
            $query->where('id', $clientId);
        }

        $count = $query->count();
        if ($count === 0) {
            $this->info('No active clients found to sync.');
            return;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $query->chunkById(100, function ($clients) use ($bar) {
            foreach ($clients as $client) {
                SyncChatbotClientDataJob::dispatch($client->id)->onQueue('chatbot');
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Chatbot sync jobs dispatched successfully.');
    }
}
