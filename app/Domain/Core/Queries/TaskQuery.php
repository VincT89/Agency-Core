<?php

namespace App\Domain\Core\Queries;

use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;

class TaskQuery
{
    /**
     * Builds the standard index query with given filters.
     * This relies upon the underlying ProjectSupremacyScope implicitly.
     */
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

    /**
     * Builds the query for the Kanban board view.
     * Often identical to forIndex but may include different eager loads or ordering.
     */
    public function forKanban(array $filters): Builder
    {
        return $this->forIndex($filters)->reorder()->orderByRaw('due_date IS NULL, due_date ASC');
    }

    /**
     * Returns an un-scoped task builder for trusted system/batch detection processes only.
     * Use with EXTREME caution.
     */
    public function forSystemBatch(): Builder
    {
        return Task::query()->withoutGlobalScope(\App\Models\Scopes\ProjectSupremacyScope::class);
    }
}
