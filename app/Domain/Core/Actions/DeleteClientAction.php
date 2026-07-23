<?php

namespace App\Domain\Core\Actions;

use App\Models\Client;
use App\Domain\Social\Actions\DeleteMarketingCampaignPostAction;
use Illuminate\Support\Facades\DB;
use Exception;

class DeleteClientAction
{
    public function __construct(
        private readonly DeleteMarketingCampaignPostAction $deletePostAction
    ) {}

    /**
     * @throws Exception If client has protected historical data
     */
    public function execute(Client $client): void
    {
        $clientId = $client->id;

        DB::transaction(function () use ($clientId) {
            $lockedClient = Client::where('id', $clientId)->lockForUpdate()->firstOrFail();

            // Lock related campaigns
            $campaigns = $lockedClient->marketingCampaigns()->lockForUpdate()->get();

            // Lock all posts BEFORE checking history
            $allPosts = collect();
            foreach ($campaigns as $campaign) {
                $posts = $campaign->posts()->lockForUpdate()->get();
                $allPosts = $allPosts->concat($posts);
            }

            // Check for historical posts using the locked scope to avoid race conditions
            foreach ($allPosts as $post) {
                if ($post->versions()->exists() || $post->publications()->exists()) {
                    throw \App\Domain\Social\Exceptions\HistoricalPostProtectedException::forPost($post);
                }
            }

            // At this point, we know there are NO historical posts.
            // But there might be draft/never-versioned posts.
            // We need to delete them securely via action.
            foreach ($allPosts as $post) {
                $this->deletePostAction->execute($post);
            }

            foreach ($campaigns as $campaign) {
                // Delete empty campaign
                $campaign->delete();
            }

            // Finally, delete the client
            $lockedClient->delete();
        });
    }
}
