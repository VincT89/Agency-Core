<?php

namespace App\Domain\Social\Actions;

use App\Models\SocialPost;
use App\Models\SocialPostComment;
use App\Models\SocialPostReviewToken;
use App\Enums\Social\SocialPostStatus;
use App\Enums\Social\SocialPostCommentVisibility;
use App\Enums\Social\SocialPostCommentType;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Exception;

class ClientRespondToSocialPostAction
{
    public function __construct(
        protected AuditLogService $auditLogger
    ) {}

    /**
     * @param SocialPostReviewToken $token
     * @param string $actionType 'approve' | 'request_changes' | 'comment'
     * @param string|null $commentBody
     * @param string|null $clientName
     * @param string|null $clientEmail
     * @return void
     */
    public function execute(
        SocialPostReviewToken $token, 
        string $actionType, 
        ?string $commentBody = null,
        ?string $clientName = null,
        ?string $clientEmail = null
    ): void {
        $post = $token->post;

        if (in_array($post->status, [SocialPostStatus::Scheduled, SocialPostStatus::Published])) {
            throw new Exception("Non puoi interagire con un post che è già stato pianificato o pubblicato.");
        }

        DB::transaction(function () use ($token, $actionType, $commentBody, $clientName, $clientEmail, $post) {

            // 1. Aggiungi il commento (se presente)
            if ($commentBody) {
                SocialPostComment::create([
                    'social_post_id' => $post->id,
                    'social_post_version_id' => $post->current_version_id,
                    'client_name' => $clientName ?? 'Cliente',
                    'client_email' => $clientEmail,
                    'body' => $commentBody,
                    'visibility' => SocialPostCommentVisibility::Client,
                    'type' => $actionType === 'request_changes' ? SocialPostCommentType::ChangeRequest : SocialPostCommentType::Comment,
                ]);
            }

            // 2. Gestisci lo stato in base all'azione
            if ($actionType === 'approve') {
                $post->update([
                    'status' => SocialPostStatus::ClientApproved,
                    'client_approved_at' => now(),
                ]);

                // Segna il token come usato (opzionale, se vogliamo permettere un solo voto)
                $token->update(['used_at' => now()]);

            } elseif ($actionType === 'request_changes') {
                $post->update([
                    'status' => SocialPostStatus::ClientChangesRequested,
                ]);
            }

            // 3. Traccia nell'audit log (anche se fatto dal cliente)
            $actionLabel = match($actionType) {
                'approve' => 'social_post.client_approved',
                'request_changes' => 'social_post.client_requested_changes',
                default => 'social_post.client_commented',
            };

            $this->auditLogger->log(
                action: $actionLabel,
                auditable: $post,
                oldValues: null,
                newValues: null,
                description: "Il cliente ha interagito con il post (Azione: {$actionType}) | Nome: " . ($clientName ?? 'Anonimo'),
                userId: null
            );

            // Notifica Admin e Social
            $usersToNotify = \App\Models\User::whereIn('role', [\App\Enums\UserRole::Admin, \App\Enums\UserRole::Marketing])->get();
            
            \Illuminate\Support\Facades\Notification::send(
                $usersToNotify, 
                new \App\Notifications\SocialPostWorkflowNotification(
                    $post, 
                    $actionType, 
                    "Il cliente ha interagito con il post: " . match($actionType) {
                        'approve' => 'Approvato',
                        'request_changes' => 'Richieste modifiche',
                        default => 'Nuovo commento'
                    }
                )
            );
        });
    }
}
