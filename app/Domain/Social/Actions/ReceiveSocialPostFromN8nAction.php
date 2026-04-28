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

            $projectId = $data['project_id'] ?? null;
            $clientId = $data['client_id'] ?? null;

            if (empty($projectId) && !empty($data['marketing_project_id'])) {
                $mp = \App\Models\MarketingProject::find($data['marketing_project_id']);
                if ($mp) {
                    $projectId = $mp->project_id;
                    $clientId = $mp->client_id;
                }
            } elseif (!empty($projectId) && empty($clientId)) {
                $clientId = $this->getClientIdFromProject($projectId);
            }

            // Check Idempotency per slot ID
            if (!empty($data['editorial_plan_slot_id'])) {
                $existingPost = SocialPost::where('editorial_plan_slot_id', $data['editorial_plan_slot_id'])->first();
                if ($existingPost) {
                    $versionAction = app(AddSocialPostVersionFromN8nAction::class);
                    $versionAction->execute($existingPost, $data);
                    return $existingPost;
                }
            }

            try {
                // 2. Crea il Social Post
                $post = SocialPost::create([
                    'external_id' => $data['external_id'] ?? null,
                    'project_id' => $projectId,
                    'client_id' => $clientId,
                    'marketing_project_id' => $data['marketing_project_id'] ?? null,
                    'editorial_plan_id' => $data['editorial_plan_id'] ?? null,
                    'editorial_plan_slot_id' => $data['editorial_plan_slot_id'] ?? null,
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

                // 5. Update Marketing models
                if ($post->marketing_project_id) {
                    $post->marketingProject->update(['status' => \App\Enums\Social\MarketingProjectStatus::PostsReceived->value]);
                }
                if ($post->editorial_plan_slot_id) {
                    $post->editorialPlanSlot->update([
                        'social_post_id' => $post->id,
                        'status' => \App\Enums\Social\EditorialPlanSlotStatus::PostReceived->value
                    ]);
                }
                if ($post->editorial_plan_id) {
                    $post->editorialPlan->update(['status' => \App\Enums\Social\EditorialPlanStatus::PostsReceived->value]);
                }

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

            } catch (\Illuminate\Database\QueryException $e) {
                if ($e->getCode() === '23000' && !empty($data['editorial_plan_slot_id'])) {
                    // Unique constraint violation on editorial_plan_slot_id
                    $existingPost = SocialPost::where('editorial_plan_slot_id', $data['editorial_plan_slot_id'])->first();
                    if ($existingPost) {
                        $versionAction = app(AddSocialPostVersionFromN8nAction::class);
                        $versionAction->execute($existingPost, $data);
                        return $existingPost;
                    }
                }
                throw $e;
            }
        });
    }

    protected function getClientIdFromProject(int $projectId): int
    {
        $project = \App\Models\Project::withoutGlobalScopes()->findOrFail($projectId);
        return $project->client_id;
    }
}
