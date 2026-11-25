<?php

namespace App\Domain\Core\Queries;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;

class TicketQuery
{
    // Costruisce la query filtrata per l'indice (ProjectSupremacyScope implicito)
    public function forIndex(array $filters): Builder
    {
        $query = Ticket::query()
            ->with(['client', 'project', 'creator', 'assignee', 'attachments'])
            ->orderByRaw("
                CASE tickets.status
                    WHEN 'open' THEN 0
                    WHEN 'in_progress' THEN 1
                    WHEN 'waiting' THEN 2
                    WHEN 'resolved' THEN 4
                    WHEN 'closed' THEN 5
                    ELSE 3
                END
            ");

        if (($filters['sort'] ?? null) === 'updated') {
            RecentActivity::apply($query)->orderByDesc('last_activity_at');
        } else {
            $query->latest('tickets.created_at');
        }
        $query->orderByDesc('tickets.id');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . strtolower($filters['search']) . '%';
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(title) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(code) LIKE ?', [$search])
                  ->orWhereHas('client', function ($cq) use ($search) {
                      $cq->whereRaw('LOWER(name) LIKE ?', [$search]);
                  })
                  ->orWhereHas('project', function ($pq) use ($search) {
                      $pq->whereRaw('LOWER(name) LIKE ?', [$search]);
                  });
            });
        }

        return $query;
    }
}
