<?php

namespace App\Domain\Social\Actions;

use App\Models\SocialPost;
use App\Enums\Social\SocialPostStatus;
use App\Enums\Social\PublicationStatus;
use App\Enums\Social\MarketingProjectStatus;
use App\Enums\Social\EditorialPlanSlotStatus;

class MarkSocialPostAsPublishedAction
{
    public function execute(SocialPost $post): void
    {
        $post->update([
            'status' => SocialPostStatus::Published,
            'publication_status' => PublicationStatus::Published,
            'published_at' => now(),
            'published_by' => auth()->id() ?? $post->published_by,
        ]);

        if ($post->marketingProject) {
            $allPublished = $post->marketingProject->socialPosts()
                ->where('id', '!=', $post->id)
                ->where('status', '!=', SocialPostStatus::Published->value)
                ->doesntExist();

            if ($allPublished) {
                $post->marketingProject->update(['status' => MarketingProjectStatus::Completed->value]);
            }
        }

        if ($post->editorialPlanSlot) {
            $post->editorialPlanSlot->update(['status' => EditorialPlanSlotStatus::Published->value]);
            event(new \App\Events\EditorialSlotPublished($post->editorialPlanSlot));
        } else {
            // Fallback for one-shot posts without a slot
            $task = \App\Models\Task::where('social_post_id', $post->id)->where('status', '!=', 'done')->first();
            if ($task && !in_array($task->status, ['done', 'cancelled', 'blocked'])) {
                $task->update(['status' => 'done', 'completed_at' => now()]);
            }
        }
    }
}
