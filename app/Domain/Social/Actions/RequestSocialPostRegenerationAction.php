<?php

namespace App\Domain\Social\Actions;

use App\Models\SocialPost;
use App\Models\SocialPostComment;
use App\Models\User;
use App\Enums\Social\SocialPostStatus;
use App\Enums\Social\SocialPostCommentVisibility;
use App\Enums\Social\SocialPostCommentType;
use App\Services\Integrations\N8n\N8nClient;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Exception;

class RequestSocialPostRegenerationAction
{
    public function __construct(
        protected N8nClient $n8nClient,
        protected AuditLogService $auditLogger
    ) {}


    public function execute(SocialPost $post, User $user, string $prompt): void
    {
        if (in_array($post->status, [SocialPostStatus::Scheduled, SocialPostStatus::Published])) {
            throw new Exception("Non puoi richiedere modifiche per un post pianificato o pubblicato. Annulla prima la pianificazione.");
        }

        DB::transaction(function () use ($post, $user, $prompt) {
            
            // Registra la richiesta come commento interno per tracciabilità
            SocialPostComment::create([
                'social_post_id' => $post->id,
                'social_post_version_id' => $post->current_version_id,
                'user_id' => $user->id,
                'body' => $prompt,
                'visibility' => SocialPostCommentVisibility::Internal,
                'type' => SocialPostCommentType::ChangeRequest,
            ]);

            // Blocca il post nello stato di rigenerazione
            $post->update([
                'status' => SocialPostStatus::Regenerating,
            ]);

            // Log di audit per sicurezza e compliance
            $this->auditLogger->log(
                action: 'social_post.regeneration_requested',
                auditable: $post,
                oldValues: null,
                newValues: null,
                description: "Ha richiesto la rigenerazione del post: {$post->title}",
                userId: $user->id
            );

            // Trasmette il comando al worker N8N
            $payload = [
                'social_post_id' => $post->id,
                'external_id' => $post->external_id,
                'project_id' => $post->project_id,
                'current_version' => $post->currentVersion->version_number ?? 1,
                'prompt' => $prompt,
            ];

            $this->n8nClient->requestSocialPostRegeneration($payload);

            // Il tracciamento dell'integrazione è demandato a N8nClient
        });
    }
}
