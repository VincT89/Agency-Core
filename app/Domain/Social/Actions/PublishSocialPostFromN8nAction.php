<?php

namespace App\Domain\Social\Actions;

use App\Models\SocialPost;
use App\Enums\Social\SocialPostStatus;
use Illuminate\Support\Facades\DB;
use App\Services\AuditLogService;

class PublishSocialPostFromN8nAction
{
    public function __construct(
        protected AuditLogService $auditLogger
    ) {}

    public function execute(SocialPost $post, array $data): SocialPost
    {
        return DB::transaction(function () use ($post, $data) {
            $lockedPost = SocialPost::where('id', $post->id)->lockForUpdate()->first();

            // Guardia Forte: Se il post è già stato pubblicato (es. retry da n8n), evitiamo di sovrascrivere o generare doppi log.
            if ($lockedPost->status === SocialPostStatus::Published) {
                if ($lockedPost->external_post_id === $data['external_post_id']) {
                    return $lockedPost;
                }
                throw new \Exception('Conflitto: il post è già pubblicato con un external_post_id differente.');
            }

            $lockedPost->update([
                'status' => SocialPostStatus::Published,
                'publication_status' => 'published',
                'published_at' => $data['published_at'],
                'published_platform' => $data['platform'],
                'external_post_id' => $data['external_post_id'],
                'external_post_url' => $data['external_post_url'] ?? null,
            ]);

            $this->auditLogger->log(
                action: 'social_post.published_from_n8n',
                auditable: $lockedPost,
                oldValues: null,
                newValues: null,
                description: "Post pubblicato da n8n sulla piattaforma {$data['platform']}",
                userId: null
            );

            return $lockedPost;
        });
    }
}
