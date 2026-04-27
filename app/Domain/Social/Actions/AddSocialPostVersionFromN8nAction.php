<?php

namespace App\Domain\Social\Actions;

use App\Models\SocialPost;
use App\Models\SocialPostVersion;
use App\Enums\Social\SocialPostStatus;
use App\Enums\Social\SocialPostSource;
use App\Services\Social\SocialImageStorageService;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Exception;

class AddSocialPostVersionFromN8nAction
{
    public function __construct(
        protected SocialImageStorageService $storageService,
        protected AuditLogService $auditLogger
    ) {}

    /**
     * @param SocialPost $post
     * @param array $data
     * @return SocialPostVersion
     * @throws Exception
     */
    public function execute(SocialPost $post, array $data): SocialPostVersion
    {
        return DB::transaction(function () use ($post, $data) {
            // Lock per evitare race condition sul numero di versione
            $lockedPost = SocialPost::where('id', $post->id)->lockForUpdate()->first();

            if (in_array($lockedPost->status, [SocialPostStatus::Scheduled, SocialPostStatus::Published])) {
                throw new Exception("Non puoi aggiungere nuove versioni a un post pianificato o pubblicato. Annulla prima la pianificazione.");
            }

            // 1. Download image
            $imagePath = null;
            if (!empty($data['image_url'])) {
                $imagePath = $this->storageService->downloadAndStore($data['image_url']);
            }

            // 2. Determina il prossimo numero di versione
            $nextVersionNumber = $lockedPost->versions()->max('version_number') + 1;

            // 3. Crea la nuova versione
            $version = SocialPostVersion::create([
                'social_post_id' => $lockedPost->id,
                'version_number' => $nextVersionNumber,
                'caption' => $data['caption'] ?? '',
                'image_path' => $imagePath,
                'original_image_url' => $data['image_url'] ?? null,
                'prompt_used' => $data['prompt_used'] ?? null,
                'source' => SocialPostSource::Regenerated,
            ]);

            // 4. Aggiorna il post corrente e lo stato
            $post->update([
                'current_version_id' => $version->id,
                'status' => SocialPostStatus::InternalReview,
            ]);

            // 5. Traccia nell'audit log
            $this->auditLogger->log(
                action: 'social_post.version_added',
                auditable: $post,
                oldValues: null,
                newValues: null,
                description: "Ricevuta nuova versione v{$nextVersionNumber} da n8n per: {$post->title}",
                userId: null
            );

            // Notificare Admin/Marketing
            $usersToNotify = \App\Models\User::whereIn('role', [\App\Enums\UserRole::Admin, \App\Enums\UserRole::Marketing])->get();
            
            \Illuminate\Support\Facades\Notification::send(
                $usersToNotify->unique('id'), 
                new \App\Notifications\SocialPostWorkflowNotification(
                    $lockedPost, 
                    'regenerated', 
                    "È stata generata una nuova versione del post tramite n8n."
                )
            );

            return $version;
        });
    }
}
