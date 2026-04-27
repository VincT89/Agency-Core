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

class ReceiveSocialPostFromN8nAction
{
    public function __construct(
        protected SocialImageStorageService $storageService,
        protected AuditLogService $auditLogger
    ) {}

    /**
     * @param array $data
     * @return SocialPost
     * @throws Exception
     */
    public function execute(array $data): SocialPost
    {
        return DB::transaction(function () use ($data) {
            
            // 1. Download image
            $imagePath = null;
            if (!empty($data['image_url'])) {
                $imagePath = $this->storageService->downloadAndStore($data['image_url']);
            }

            // 2. Crea il Social Post
            $post = SocialPost::create([
                'external_id' => $data['external_id'] ?? null,
                'project_id' => $data['project_id'],
                'client_id' => $data['client_id'] ?? $this->getClientIdFromProject($data['project_id']),
                'title' => $data['title'],
                'format' => $data['format'] ?? '1080x1350',
                'source' => SocialPostSource::N8n,
                'status' => SocialPostStatus::Received,
            ]);

            // 3. Crea la prima versione
            $version = SocialPostVersion::create([
                'social_post_id' => $post->id,
                'version_number' => 1,
                'caption' => $data['caption'] ?? '',
                'image_path' => $imagePath,
                'original_image_url' => $data['image_url'] ?? null,
                'source' => SocialPostSource::N8n,
            ]);

            // 4. Aggiorna il post corrente e passa a InternalReview
            $post->update([
                'current_version_id' => $version->id,
                'status' => SocialPostStatus::InternalReview,
            ]);

            $this->auditLogger->log(
                action: 'social_post.received',
                auditable: $post,
                oldValues: null,
                newValues: null,
                description: "Ricevuto nuovo post Social da n8n: {$post->title}",
                userId: null
            );

            // Notifica Admin e Social
            $usersToNotify = \App\Models\User::whereIn('role', [\App\Enums\UserRole::Admin, \App\Enums\UserRole::Marketing])->get();

            \Illuminate\Support\Facades\Notification::send(
                $usersToNotify, 
                new \App\Notifications\SocialPostWorkflowNotification($post, 'received', 'Nuovo post ricevuto da n8n')
            );

            return $post;
        });
    }

    protected function getClientIdFromProject(int $projectId): int
    {
        $project = \App\Models\Project::withoutGlobalScopes()->findOrFail($projectId);
        return $project->client_id;
    }
}
