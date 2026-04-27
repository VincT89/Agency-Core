<?php

namespace App\Observers;

use App\Models\Project;
use App\Services\AuditLogService;

class ProjectObserver
{
    public function __construct(protected AuditLogService $auditLog) {}

    public function created(Project $project): void
    {
        $this->auditLog->log('created', $project, null, $project->getAttributes());
    }

    public function updated(Project $project): void
    {
        $old = array_intersect_key($project->getOriginal(), $project->getDirty());
        $new = $project->getDirty();

        $action = isset($new['status']) ? 'status_changed' : 'updated';

        $this->auditLog->log($action, $project, $old, $new);
    }

    public function deleted(Project $project): void
    {
        $this->auditLog->log('deleted', $project, $project->getOriginal(), null);
    }
}
