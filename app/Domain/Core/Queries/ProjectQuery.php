<?php

namespace App\Domain\Core\Queries;

use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;

class ProjectQuery
{
    public function forIndex(array $filters = []): Builder
    {
        $query = Project::query()
            ->with('client')
            ->withCount(['tasks', 'tickets']);

        if (!empty($filters['search'])) {
            $searchStr = '%' . strtolower($filters['search']) . '%';
            $query->where(function ($q) use ($searchStr) {
                $q->whereRaw('LOWER(name) LIKE ?', [$searchStr])
                    ->orWhereHas('client', function ($cq) use ($searchStr) {
                        $cq->whereRaw('LOWER(name) LIKE ?', [$searchStr]);
                    });
            });
        }

        return $query;
    }
}
