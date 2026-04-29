<?php

namespace App\Domain\Social\Actions;

use App\Models\SocialPost;
use App\Models\EditorialSlot;
use App\Models\User;
use App\Enums\Social\SocialPostStatus;
use App\Enums\Social\EditorialSlotStatus;
use App\Enums\Social\SocialPlatform;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use App\Services\AuditLogService;
use Exception;

class ScheduleSocialPostAction
{
    public function __construct(
        protected AuditLogService $auditLogger
    ) {}
    public function execute(SocialPost $post, string $scheduledAt, SocialPlatform $platform, ?string $notes, User $user): EditorialSlot
    {
        Gate::forUser($user)->authorize('schedule', $post);

        return DB::transaction(function () use ($post, $scheduledAt, $platform, $notes, $user) {
            // Blocca il record in scrittura per evitare doppie pianificazioni simultanee
            $lockedPost = SocialPost::whereKey($post->id)->lockForUpdate()->first();

            if (!$lockedPost->isPlannable()) {
                throw new Exception("Il post non può essere pianificato. Assicurati che sia approvato e non abbia già slot attivi.");
            }

            // Crea lo slot di pubblicazione effettivo per la piattaforma
            $slot = EditorialSlot::create([
                'project_id' => $lockedPost->project_id,
                'social_post_id' => $lockedPost->id,
                'scheduled_at' => $scheduledAt,
                'platform' => $platform,
                'status' => EditorialSlotStatus::Scheduled,
                'notes' => $notes,
                'created_by' => $user->id,
            ]);

            // Aggiorna lo stato del post a pianificato
            $lockedPost->update([
                'status' => SocialPostStatus::Scheduled,
            ]);

            $this->auditLogger->log(
                action: 'social_post.scheduled',
                auditable: $lockedPost,
                oldValues: null,
                newValues: ['slot_id' => $slot->id, 'scheduled_at' => $scheduledAt, 'platform' => $platform->value],
                description: "Post pianificato su {$platform->label()} per il " . \Carbon\Carbon::parse($scheduledAt)->format('d/m/Y H:i'),
                userId: $user->id
            );

            // Notifica il team dell'avvenuta pianificazione
            $usersToNotify = \App\Models\User::whereIn('role', [\App\Enums\UserRole::Admin, \App\Enums\UserRole::Marketing])->get();
            
            \Illuminate\Support\Facades\Notification::send(
                $usersToNotify->unique('id'), 
                new \App\Notifications\SocialPostWorkflowNotification(
                    $lockedPost, 
                    'scheduled', 
                    "Il post è stato pianificato per il " . \Carbon\Carbon::parse($scheduledAt)->format('d/m/Y H:i')
                )
            );

            return $slot;
        });
    }
}
