<?php

namespace App\Domain\Social\Actions;

use App\Models\EditorialSlot;
use App\Models\User;
use App\Models\SocialPost;
use App\Enums\Social\SocialPostStatus;
use App\Enums\Social\EditorialSlotStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use App\Services\AuditLogService;
use Exception;

class MarkEditorialSlotPublishedAction
{
    public function __construct(
        protected AuditLogService $auditLogger
    ) {}
    public function execute(EditorialSlot $slot, User $user): EditorialSlot
    {
        Gate::forUser($user)->authorize('publish', $slot);

        return DB::transaction(function () use ($slot, $user) {
            $lockedSlot = EditorialSlot::whereKey($slot->id)->lockForUpdate()->first();

            if ($lockedSlot->status !== EditorialSlotStatus::Scheduled) {
                throw new Exception("Solo uno slot pianificato può essere marcato come pubblicato.");
            }

            $lockedSlot->update([
                'status' => EditorialSlotStatus::Published,
                'published_at' => now(),
            ]);

            $post = SocialPost::whereKey($lockedSlot->social_post_id ?: $lockedSlot->post_id)->lockForUpdate()->first();

            $post->update([
                'status' => SocialPostStatus::Published,
            ]);

            $this->auditLogger->log(
                action: 'social_post.slot_published',
                auditable: $post,
                oldValues: null,
                newValues: ['slot_id' => $lockedSlot->id],
                description: "Post pubblicato definitivamente (slot del " . \Carbon\Carbon::parse($lockedSlot->scheduled_at)->format('d/m/Y H:i') . ")",
                userId: $user->id
            );

            // Notifica il team dell'avvenuta pubblicazione
            $usersToNotify = \App\Models\User::whereIn('role', [\App\Enums\UserRole::Admin, \App\Enums\UserRole::Marketing])->get();
            
            \Illuminate\Support\Facades\Notification::send(
                $usersToNotify->unique('id'), 
                new \App\Notifications\SocialPostWorkflowNotification(
                    $post, 
                    'slot_published', 
                    "Il post è stato pubblicato sui social!"
                )
            );

            event(new \App\Events\EditorialSlotPublished($lockedSlot));

            return $lockedSlot;
        });
    }
}
