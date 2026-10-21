<?php

namespace App\Domain\Finance\Queries;

use App\Models\Client;
use App\Models\Expense;
use App\Models\HostingService;
use App\Models\Project;
use App\Models\Task;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;

class ExpenseIndexQuery
{
    public function query(array $filters, bool $categoryFilter = true): Builder
    {
        $query = Expense::query();
        foreach (['status', 'supplier', 'expense_recurrence_id'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $query->where($field, $filters[$field]);
            }
        }
        if ($categoryFilter && filled($filters['category_id'] ?? null)) {
            $filters['category_id'] === 'none' ? $query->whereNull('category_id') : $query->where('category_id', $filters['category_id']);
        }
        if ($categoryFilter && filled($filters['subcategory_id'] ?? null)) {
            $filters['subcategory_id'] === 'none' ? $query->whereNull('subcategory_id') : $query->where('subcategory_id', $filters['subcategory_id']);
        }
        $types = ['client' => Client::class, 'project' => Project::class, 'task' => Task::class, 'ticket' => Ticket::class, 'hosting_service' => HostingService::class];
        if (isset($types[$filters['expenseable_type'] ?? ''])) {
            $query->where('expenseable_type', $types[$filters['expenseable_type']]);
        }
        $query->when(filled($filters['from'] ?? null), fn ($q) => $q->whereDate('expense_date', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->whereDate('expense_date', '<=', $filters['to']))
            ->when(filled($filters['min_amount'] ?? null), fn ($q) => $q->where('amount', '>=', $filters['min_amount']))
            ->when(filled($filters['max_amount'] ?? null), fn ($q) => $q->where('amount', '<=', $filters['max_amount']));
        if (filled($filters['search'] ?? null)) {
            $search = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters['search'])).'%';
            $query->where(fn ($q) => $q->whereRaw("LOWER(title) LIKE ? ESCAPE '!'", [$search])
                ->orWhereRaw("LOWER(supplier) LIKE ? ESCAPE '!'", [$search])
                ->orWhereHas('categoryGroup', fn ($c) => $c->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$search]))
                ->orWhereHas('subcategory', fn ($c) => $c->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$search])));
        }
        if (filled($filters['client_id'] ?? null)) {
            $clientId = (int) $filters['client_id'];
            $query->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('expenseable_type', Client::class)->where('expenseable_id', $clientId))
                ->orWhereHasMorph('expenseable', [Project::class, HostingService::class, Ticket::class], fn ($q) => $q->where('client_id', $clientId))
                ->orWhereHasMorph('expenseable', [Task::class], fn ($q) => $q->whereHas('project', fn ($p) => $p->where('client_id', $clientId))));
        }
        if (filled($filters['project_id'] ?? null)) {
            $projectId = (int) $filters['project_id'];
            $query->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('expenseable_type', Project::class)->where('expenseable_id', $projectId))
                ->orWhereHasMorph('expenseable', [Task::class, Ticket::class], fn ($q) => $q->where('project_id', $projectId)));
        }
        return $query;
    }
}
