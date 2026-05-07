<?php

namespace App\Domain\Chatbot\Actions;

use App\Models\Client;
use Illuminate\Support\Facades\DB;

class SyncFullChatbotClientAction
{
    public function __construct(
        private SyncChatbotClientAction $syncClientAction,
        private SyncChatbotProjectsAction $syncProjectsAction,
        private SyncChatbotMarketingCampaignsAction $syncCampaignsAction,
        private SyncChatbotMarketingPostsAction $syncPostsAction,
        private SyncChatbotTicketsAction $syncTicketsAction
    ) {
    }

    public function execute(Client $client): void
    {
        DB::transaction(function () use ($client) {
            $chatbotClient = $this->syncClientAction->execute($client);
            $this->syncProjectsAction->execute($client, $chatbotClient);
            $this->syncCampaignsAction->execute($client, $chatbotClient);
            $this->syncPostsAction->execute($client, $chatbotClient);
            $this->syncTicketsAction->execute($client, $chatbotClient);
        });
    }
}
