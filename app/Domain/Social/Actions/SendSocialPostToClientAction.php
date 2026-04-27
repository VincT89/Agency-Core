<?php

namespace App\Domain\Social\Actions;

use App\Models\SocialPost;
use App\Models\SocialPostReviewToken;
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
            
            SocialPostReviewToken::create([
                'social_post_id' => $post->id,
                'token' => $tokenString,
                'expires_at' => now()->addDays(30),
            ]);

            // 2. Aggiorna stato e timestamp
            $post->update([
                'status' => SocialPostStatus::SentToClient,
                'sent_to_client_at' => now(),
            ]);

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

            return route('client.social-posts.review', ['token' => $tokenString]);
        });
    }
}
