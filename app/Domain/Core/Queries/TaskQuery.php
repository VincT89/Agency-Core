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

    // Bypass di sicurezza globale per processi di sistema in background
    public function forSystemBatch(): Builder
    {
        return Task::query()->withoutGlobalScope(\App\Models\Scopes\ProjectSupremacyScope::class);
    }
}
