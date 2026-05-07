<?php

namespace App\Observers;

use App\Models\Project;
use App\Services\AuditLogService;
use App\Jobs\Chatbot\SyncChatbotClientDataJob;

class ProjectObserver
{
    public function __construct(protected AuditLogService $auditLog) {}

    private function syncChatbot(Project $project): void
    {
        if ($project->client_id) {
            SyncChatbotClientDataJob::dispatch($project->client_id)
                ->onQueue('chatbot')
                ->delay(now()->addSeconds(10));
        }
    }

    public function created(Project $project): void
    {
        $this->auditLog->log('created', $project, null, $project->getAttributes());
        $this->syncChatbot($project);
    }

    public function updated(Project $project): void
    {
        $old = array_intersect_key($project->getOriginal(), $project->getDirty());
        $new = $project->getDirty();

        $action = isset($new['status']) ? 'status_changed' : 'updated';

        $this->auditLog->log($action, $project, $old, $new);
        $this->syncChatbot($project);
    }

    public function deleted(Project $project): void
    {
        $clientId = $project->client_id;
        $this->auditLog->log('deleted', $project, $project->getOriginal(), null);

        if ($clientId) {
            SyncChatbotClientDataJob::dispatch($clientId)
                ->onQueue('chatbot')
                ->delay(now()->addSeconds(10));
        }
    }
}
