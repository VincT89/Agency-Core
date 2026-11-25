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
                CASE tasks.status
                    WHEN 'todo' THEN 0
                    WHEN 'in_progress' THEN 1
                    WHEN 'waiting' THEN 2
                    WHEN 'review' THEN 3
                    WHEN 'done' THEN 5
                    WHEN 'cancelled' THEN 6
                    ELSE 4
                END
            ");

        if (($filters['sort'] ?? null) === 'updated') {
            /** I commenti interni dei task restano riservati anche nell'ordinamento destinato ai commerciali. */
            RecentActivity::apply($query, ! auth()->user()?->isCommercial())
                ->orderByDesc('last_activity_at');
        } else {
            $query->latest('tasks.created_at');
        }
        $query->orderByDesc('tasks.id');

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
        $query = $this->forIndex($filters);

        return ($filters['sort'] ?? null) === 'updated'
            ? $query
            : $query->reorder()->orderByRaw('due_date IS NULL, due_date ASC')->orderByDesc('tasks.id');
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
