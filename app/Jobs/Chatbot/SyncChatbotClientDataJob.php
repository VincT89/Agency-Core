<?php

namespace App\Jobs\Chatbot;

use App\Models\Client;
use App\Domain\Chatbot\Actions\SyncFullChatbotClientAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncChatbotClientDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;

    public function __construct(public int $clientId)
    {
        $this->onQueue('chatbot');
    }

    public function handle(SyncFullChatbotClientAction $action): void
    {
        $client = Client::query()
            ->where('status', 'active')
            ->find($this->clientId);

        if (! $client) {
            return;
        }

        $action->execute($client);
    }
}
