<?php

namespace App\Domain\Shooting\Queries;

use App\Models\Shooting\Shoot;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;

class ShootQuery
{
    public function forIndex(array $filters = []): Builder
    {
        return $this->applyFilters($this->base(), $filters);
    }

    public function forProject(Project $project, array $filters = []): Builder
    {
        return $this->applyFilters(
            $this->base()->where('project_id', $project->id), 
            $filters
        );
    }

    protected function base(): Builder
    {
        $query = Shoot::query()
            ->with(['project', 'photographer', 'creator', 'selectedSlot']);
            
        return $query;
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', $search)
                  ->orWhere('code', 'like', $search)
                  ->orWhere('location', 'like', $search);
            });
        }

        if (!empty($filters['workflow_filter'])) {
            $wf = $filters['workflow_filter'];
            
            if ($wf === 'attesa_fotografo') {
                $query->where('status', \App\Enums\Shooting\ShootStatus::WaitingPhotographer);
            } elseif ($wf === 'attesa_cliente') {
                $query->where('status', \App\Enums\Shooting\ShootStatus::WaitingClient);
            } elseif ($wf === 'confermati') {
                $query->whereIn('status', [
                    \App\Enums\Shooting\ShootStatus::ClientConfirmed,
                    \App\Enums\Shooting\ShootStatus::Scheduled,
                ]);
            } elseif ($wf === 'rifiutati_cliente') {
                $query->where('status', \App\Enums\Shooting\ShootStatus::ClientRejected);
            } elseif ($wf === 'annullati') {
                $query->where('status', \App\Enums\Shooting\ShootStatus::Cancelled);
            }
        }

        return $query;
    }
}
