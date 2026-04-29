<?php

namespace App\Domain\Social\Actions;

use App\Models\SocialPost;
use App\Models\SocialPostVersion;
use App\Enums\Social\SocialPostStatus;
use App\Enums\Social\SocialPostSource;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Exception;

class ReceiveSocialPostFromN8nAction
{
    public function __construct(
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
            if ($existing = $this->checkIdempotency($data['n8n_execution_id'] ?? null)) {
                return $existing;
            }

            $mp = $this->resolveMarketingContext($data['marketing_project_id']);
            $post = $this->createOrReusePost($data, $mp);

            try {
                $version = $this->createVersion($post, $data);
            } catch (\Illuminate\Database\QueryException $e) {
                if ($e->getCode() === '23000' && !empty($data['n8n_execution_id'])) {
                    if ($existing = $this->checkIdempotency($data['n8n_execution_id'])) {
                        return $existing;
                    }
                }
                throw $e;
            }

            $this->finalizePostAndNotify($post, $version);

            return $post;
        });
    }

    private function checkIdempotency(?string $executionId): ?array
    {
        if (!$executionId) {
            return null;
        }

        $existingVersion = SocialPostVersion::where('external_id', $executionId)->first();
        if ($existingVersion) {
            return [
                'idempotent' => true,
                'social_post' => $existingVersion->post,
                'version' => $existingVersion,
            ];
        }

        return null;
    }

    private function resolveMarketingContext(int $marketingProjectId): \App\Models\MarketingProject
    {
        $mp = \App\Models\MarketingProject::findOrFail($marketingProjectId);
        
        if (! $mp->project_id) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'marketing_project_id' => 'Il progetto marketing non è collegato a un progetto gestionale.',
            ]);
        }

        return $mp;
    }

    private function createOrReusePost(array $data, \App\Models\MarketingProject $mp): SocialPost
    {
        $projectId = $mp->project_id;
        $clientId = $mp->client_id;

        if (!empty($data['editorial_plan_slot_id'])) {
            $post = SocialPost::where('editorial_plan_slot_id', $data['editorial_plan_slot_id'])->first();
            
            if ($post) {
                return $post;
            }

            return SocialPost::firstOrCreate(
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
                    'publication_mode' => $mp->publication_mode ?? \App\Enums\Social\PublicationMode::Manual,
                ]
            );
        }

        return SocialPost::create([
            'project_id' => $projectId,
            'client_id' => $clientId,
            'marketing_project_id' => $mp->id,
            'title' => $data['title'],
            'format' => $data['format'] ?? '1080x1350',
            'source' => SocialPostSource::N8n,
            'status' => SocialPostStatus::InternalReview,
            'publication_mode' => $mp->publication_mode ?? \App\Enums\Social\PublicationMode::Manual,
        ]);
    }

    private function createVersion(SocialPost $post, array $data): SocialPostVersion
    {
        if (!$post->wasRecentlyCreated && $post->versions()->exists()) {
            $versionAction = app(AddSocialPostVersionFromN8nAction::class);
            return $versionAction->execute($post, $data);
        }

        return SocialPostVersion::create([
            'social_post_id' => $post->id,
            'external_id' => $data['n8n_execution_id'] ?? null,
            'version_number' => $post->versions()->count() ? $post->versions()->max('version_number') + 1 : 1,
            'caption' => $data['caption'] ?? '',
            'image_path' => null,
            'original_image_url' => $data['image_url'] ?? null,
            'source' => SocialPostSource::N8n,
        ]);
    }

    private function finalizePostAndNotify(SocialPost $post, SocialPostVersion $version): void
    {
        $post->update([
            'current_version_id' => $version->id,
            'status' => SocialPostStatus::InternalReview,
        ]);

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

        $usersToNotify = \App\Models\User::whereIn('role', [\App\Enums\UserRole::Admin, \App\Enums\UserRole::Marketing])->get();

        \Illuminate\Support\Facades\Notification::send(
            $usersToNotify, 
            new \App\Notifications\SocialPostWorkflowNotification($post, 'received', 'Nuovo post ricevuto da n8n')
        );
    }
}
