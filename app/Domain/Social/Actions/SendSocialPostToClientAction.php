<?php

namespace App\Domain\Social\Actions;

use App\Models\SocialPost;
use App\Models\ClientReviewToken;
use App\Models\User;
use App\Enums\Social\SocialPostStatus;
use App\Services\AuditLogService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class SendSocialPostToClientAction
{
    public function __construct(
        protected AuditLogService $auditLogger
    ) {}

    /**
     * @param SocialPost $post
     * @param User $user
     * @return string L'URL pubblico per il cliente
     */
    public function execute(SocialPost $post, User $user): string
    {
        return DB::transaction(function () use ($post, $user) {
            
            // 1. Genera un token pubblico sicuro
            $tokenString = Str::random(40);
            
            ClientReviewToken::create([
                'reviewable_id' => $post->id,
                'reviewable_type' => get_class($post),
                'token' => $tokenString,
                'expires_at' => now()->addDays(30),
            ]);

            // 2. Aggiorna stato e timestamp
            $post->update([
                'status' => SocialPostStatus::SentToClient,
                'sent_to_client_at' => now(),
            ]);

            if ($post->marketing_project_id) {
                $post->marketingProject->update(['status' => \App\Enums\Social\MarketingProjectStatus::SentToClient->value]);
            }
            if ($post->editorial_plan_slot_id) {
                $post->editorialPlanSlot->update(['status' => \App\Enums\Social\EditorialPlanSlotStatus::SentToClient->value]);
            }

            // 3. Traccia nell'audit log
            $this->auditLogger->log(
                action: 'social_post.sent_to_client',
                auditable: $post,
                oldValues: null,
                newValues: null,
                description: "Ha inviato il post al cliente: {$post->title}",
                userId: $user->id
            );

            // In futuro qui potremo implementare l'invio fisico del messaggio WhatsApp o Email
            // tramite n8n o altri driver. Per ora generiamo l'URL.

            return route('client.review', ['token' => $tokenString]);
        });
    }
}
