<?php

namespace App\Domain\Core\Queries;

use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;

class TaskQuery
{
    // Costruisce la query per la lista applicando i filtri (ProjectSupremacyScope implicito)
    public function forIndex(array $filters): Builder
    {
        $query = Task::query()
            ->with(['project.client', 'assignee', 'creator'])
            ->orderByRaw("
                CASE
                    WHEN status = 'done' THEN 1
                    ELSE 0
                END
            ")
            ->latest();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }
        if (!empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }
        if (!empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        return $query;
    }

    // Costruisce la query ottimizzata per la vista Kanban
    public function forKanban(array $filters): Builder
    {
        return $this->forIndex($filters)->reorder()->orderByRaw('due_date IS NULL, due_date ASC');
    }

    public function forSidebar(int $projectId): Builder
    {
        return Task::query()
            ->where('project_id', $projectId)
            ->visibleTo(request()->user())
            ->with(['assignee'])
            ->orderByRaw("
                CASE status
                    WHEN 'in_progress' THEN 1
                    WHEN 'review' THEN 2
                    WHEN 'waiting' THEN 3
                    WHEN 'todo' THEN 4
                    WHEN 'done' THEN 5
                    WHEN 'cancelled' THEN 6
                    ELSE 7
                END
            ")
            ->orderBy('due_date', 'asc')
            ->latest('updated_at')
            ->limit(50);
    }

    // Bypass di sicurezza globale per processi di sistema in background
    public function forSystemBatch(): Builder
    {
        return Task::query()->withoutGlobalScope(\App\Models\Scopes\ProjectSupremacyScope::class);
    }
}
