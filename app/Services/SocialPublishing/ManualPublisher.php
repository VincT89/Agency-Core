<?php

namespace App\Services\SocialPublishing;

use App\Contracts\SocialPublisher;
use App\Models\SocialPost;
use App\Models\ClientSocialAccount;
use App\DTO\Social\PublicationResult;
use App\Domain\Social\Actions\MarkSocialPostAsPublishedAction;
use Carbon\Carbon;

class ManualPublisher implements SocialPublisher
{
    public function __construct(private MarkSocialPostAsPublishedAction $markPublishedAction) {}

    public function publish(SocialPost $post, ClientSocialAccount $account): PublicationResult
    {
        // Wrapper for manual flow. The actual publishing happens manually by the user,
        // so we just mark the post as published in our system.
        $this->markPublishedAction->execute($post);

        return new PublicationResult(
            success: true,
            error: null,
            raw: ['method' => 'manual', 'message' => 'Post contrassegnato manualmente come pubblicato']
        );
    }

    public function schedule(SocialPost $post, ClientSocialAccount $account, Carbon $scheduledAt): PublicationResult
    {
        // Non supportato in manuale
        return new PublicationResult(
            success: false,
            error: 'La programmazione non è supportata per la pubblicazione manuale.'
        );
    }
}
