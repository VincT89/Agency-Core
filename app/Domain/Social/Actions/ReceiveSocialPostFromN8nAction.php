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
     * @return SocialPost|array
     * @throws Exception
     */
    public function execute(array $data): SocialPost|array
    {
        return DB::transaction(function () use ($data) {
            // 1. Idempotency Check on SocialPostVersion
            $existingVersion = SocialPostVersion::where('external_id', $data['n8n_execution_id'])->first();
            if ($existingVersion) {
                return [
                    'idempotent' => true,
                    'social_post' => $existingVersion->post,
                    'version' => $existingVersion,
                ];
            }

            // 2. Fetch Marketing Project Context
            $mp = \App\Models\MarketingProject::findOrFail($data['marketing_project_id']);
            $projectId = $mp->project_id;
            $clientId = $mp->client_id;

            // 3. Download image
            $imagePath = null;
            if (!empty($data['image_url'])) {
                $imagePath = $this->storageService->downloadAndStore($data['image_url']);
            }

            $post = null;

            // 4. Create or reuse Social Post
            if (!empty($data['editorial_plan_slot_id'])) {
                $post = SocialPost::where('editorial_plan_slot_id', $data['editorial_plan_slot_id'])->first();
                
                if ($post) {
                    $versionAction = app(AddSocialPostVersionFromN8nAction::class);
                    $version = $versionAction->execute($post, $data);
                    return $post;
                }

                // If not found, create it with firstOrCreate to avoid race conditions
                $post = SocialPost::firstOrCreate(
                    ['editorial_plan_slot_id' => $data['editorial_plan_slot_id']],
                    [
                        'project_id' => $projectId,
                        'client_id' => $clientId,
                        'marketing_project_id' => $mp->id,
                        'editorial_plan_id' => $data['editorial_plan_id'] ?? null,
                        'title' => $data['title'],
                        'format' => $data['format'] ?? '1080x1350',
                        'source' => SocialPostSource::N8n,
                        'status' => SocialPostStatus::InternalReview,
                    ]
                );

                // If firstOrCreate actually returned an existing one because of a race condition,
                // we should check if it already has versions, but normally it's brand new.
                if (!$post->wasRecentlyCreated && $post->versions()->exists()) {
                    $versionAction = app(AddSocialPostVersionFromN8nAction::class);
                    $version = $versionAction->execute($post, $data);
                    return $post;
                }
            } else {
                $post = SocialPost::create([
                    'project_id' => $projectId,
                    'client_id' => $clientId,
                    'marketing_project_id' => $mp->id,
                    'title' => $data['title'],
                    'format' => $data['format'] ?? '1080x1350',
                    'source' => SocialPostSource::N8n,
                    'status' => SocialPostStatus::InternalReview,
                ]);
            }

            // 5. Create the first version
            try {
                $version = SocialPostVersion::create([
                    'social_post_id' => $post->id,
                    'external_id' => $data['n8n_execution_id'],
                    'version_number' => 1,
                    'caption' => $data['caption'] ?? '',
                    'image_path' => $imagePath,
                    'original_image_url' => $data['image_url'] ?? null,
                    'source' => SocialPostSource::N8n,
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                if ($e->getCode() === '23000' && !empty($data['n8n_execution_id'])) {
                    // Identical execution hit a race condition and was inserted by another thread
                    $existingVersion = SocialPostVersion::where('external_id', $data['n8n_execution_id'])->first();
                    if ($existingVersion) {
                        return [
                            'idempotent' => true,
                            'social_post' => $existingVersion->post,
                            'version' => $existingVersion,
                        ];
                    }
                }
                throw $e;
            }

            // 6. Update current post and move to InternalReview
            $post->update([
                'current_version_id' => $version->id,
                'status' => SocialPostStatus::InternalReview,
            ]);

            // 7. Update Marketing models
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

            // Notify Admin and Social
            $usersToNotify = \App\Models\User::whereIn('role', [\App\Enums\UserRole::Admin, \App\Enums\UserRole::Marketing])->get();

            \Illuminate\Support\Facades\Notification::send(
                $usersToNotify, 
                new \App\Notifications\SocialPostWorkflowNotification($post, 'received', 'Nuovo post ricevuto da n8n')
            );

            return $post;
        });
    }
}
