<?php

namespace App\Http\Controllers;

use App\Http\Requests\FinanceReportRequest;
use App\Models\Client;
use App\Models\ExpenseCategory;
use App\Models\Project;
use App\Services\FinanceReportQuery;
use Illuminate\Support\Arr;

class EconomicSummaryController extends Controller
{
    public function __invoke(FinanceReportRequest $request, FinanceReportQuery $reports)
    {
        return $this->render($request, $reports);
    }

    public function show(FinanceReportRequest $request, FinanceReportQuery $reports, string $group, int $id)
    {
        $subject = $group === 'clients' ? Client::findOrFail($id) : Project::findOrFail($id);
        return $this->render($request, $reports, $subject);
    }

    private function render(FinanceReportRequest $request, FinanceReportQuery $reports, Client|Project|null $subject = null)
    {
        $filters = array_filter($request->validated(), fn ($value) => $value !== null && $value !== '');
        if ($subject) {
            $filters[$subject instanceof Client ? 'client_id' : 'project_id'] = $subject->id;
        }
        $group = $filters['group'] ?? 'clients';
        $partial = ! $subject && $request->header('X-Finance-Partial') === 'report-directory';
        $sort = $filters['sort'] ?? 'turnover_desc';
        $sortColumn = match ($sort) { 'name_asc', 'name_desc' => $group === 'projects' ? 'project_name' : 'client_name', 'pending_desc' => 'total_outstanding', 'costs_desc' => 'total_expenses', default => 'total_invoiced' };
        $direction = str_ends_with($sort, '_asc') ? 'asc' : 'desc';
        $directories = [];
        foreach (['clients', 'projects'] as $kind) {
            if ($partial && $group !== $kind) {
                continue;
            }
            $query = $reports->groups($kind, $filters);
            $nameColumn = $kind === 'projects' ? 'projects.name' : 'clients.name';
            if (filled($filters['directory_search'] ?? null)) {
                $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters['directory_search'])).'%';
                $query->whereRaw("LOWER($nameColumn) LIKE ? ESCAPE '!'", [$term]);
            }
            $column = in_array($sortColumn, ['client_name', 'project_name'], true) ? $nameColumn : $sortColumn;
            $directories[$kind] = $query->orderBy($column, $direction)->orderBy($kind.'.id')->paginate(20)->withQueryString();
        }
        $categoryQuery = $reports->categoryGroups($filters);
        if (filled($filters['directory_search'] ?? null)) {
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters['directory_search'])).'%';
            $categoryQuery->whereRaw("LOWER(COALESCE(category.name, 'Senza categoria')) LIKE ? ESCAPE '!'", [$term]);
        }
        $categories = $group === 'categories' ? $categoryQuery
            ->orderBy(in_array($sort, ['name_asc', 'name_desc'], true) ? 'name' : 'total_expenses', $sort === 'name_asc' ? 'asc' : 'desc')
            ->orderBy('expenses.category_id')->paginate(20)->withQueryString() : null;
        if ($partial) {
            return response()->json(['html' => view('economic-summary.partials.directory', [
                'filters' => $filters, 'baseFilters' => Arr::except($filters, ['page', 'documents_page']),
                'from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null, 'group' => $group,
                'directory' => $categories ?? $directories[$group],
            ])->render()]);
        }
        $comparisonFilters = null;
        if (isset($filters['compare_from'], $filters['compare_to'])) {
            $comparisonFilters = array_replace($filters, ['from' => $filters['compare_from'], 'to' => $filters['compare_to']]);
        }
        $chartData = $reports->monthly($filters);
        $comparison = $comparisonFilters ? $reports->totals($comparisonFilters) : null;
        $comparisonChart = $comparisonFilters ? $reports->monthly($comparisonFilters) : null;
        $charts = app(\App\Services\FinanceReportCharts::class);
        $documents = ($subject || $request->boolean('documents')) ? $reports->documents($filters)->paginate(20, ['*'], 'documents_page')->withQueryString() : null;
        $baseFilters = Arr::except($filters, ['page', 'documents_page']);
        return view('economic-summary.index', [
            'filters' => $filters, 'baseFilters' => $baseFilters, 'subject' => $subject,
            'from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null, 'group' => $group,
            'globalSummary' => $reports->totals($filters), 'chartData' => $chartData,
            'comparison' => $comparison, 'comparisonChart' => $comparisonChart,
            'trendCharts' => $charts->trends($chartData, $comparisonChart),
            'distributionCharts' => $charts->distributions($reports, $filters, $group, (bool) $subject),
            'directory' => $categories ?? $directories[$group], 'documents' => $documents,
            'summaryByClient' => $directories['clients']->getCollection(), 'summaryByProject' => $directories['projects']->getCollection(),
            'clientLabel' => isset($filters['client_id']) ? Client::find($filters['client_id'])?->name : '',
            'projectLabel' => isset($filters['project_id']) ? Project::find($filters['project_id'])?->name : '',
            'expenseCategories' => ExpenseCategory::whereNull('parent_id')->orderBy('name')->get(['id', 'name']),
            'expenseSubcategories' => ExpenseCategory::whereNotNull('parent_id')->where('parent_id', $filters['category_id'] ?? 0)->orderBy('name')->get(['id', 'name']),
            'sparklineData' => json_encode(['labels' => $chartData['labels'], 'invoiced' => $chartData['series'][0]['data'],
                'collected' => $chartData['series'][1]['data'], 'pending' => $chartData['series'][2]['data']], JSON_THROW_ON_ERROR),
        ]);
    }
}
