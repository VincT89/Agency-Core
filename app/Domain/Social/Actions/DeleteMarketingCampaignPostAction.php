<?php

namespace App\Domain\Social\Actions;

use App\Models\MarketingCampaignPost;
use Illuminate\Support\Facades\DB;
use Exception;

class DeleteMarketingCampaignPostAction
{
    public function __construct(
        private readonly DeleteMarketingCampaignPostMediaAction $deleteMediaAction
    ) {}

    public function execute(MarketingCampaignPost $post): void
    {
        $postId = $post->id;

        DB::transaction(function () use ($postId) {
            $lockedPost = MarketingCampaignPost::where('id', $postId)->lockForUpdate()->firstOrFail();

            if ($lockedPost->versions()->exists() || $lockedPost->publications()->exists()) {
                throw \App\Domain\Social\Exceptions\HistoricalPostProtectedException::forPost($lockedPost);
            }

            // Post has no history. We can delete orphan media securely.
            // Since we locked the post, its media won't change.
            $mediaItems = $lockedPost->mediaItems()->get();
            
            foreach ($mediaItems as $media) {
                // The DeleteMarketingCampaignPostMediaAction also runs in a transaction,
                // but nested transactions are fine in Laravel.
                $this->deleteMediaAction->execute($media);
            }

            // Finally, delete the post record.
            $lockedPost->delete();
        });
    }
}
