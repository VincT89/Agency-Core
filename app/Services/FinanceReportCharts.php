<?php

namespace App\Services;

use ArielMejiaDev\LarapexCharts\LarapexChart;
use Illuminate\Support\Arr;

class FinanceReportCharts
{
    private const COLORS = ['#8870d9', '#18a88a', '#e97822', '#bd1034', '#5479b8', '#64748b'];

    public function trends(array $data, ?array $comparison): array
    {
        return array_map(function (array $series, int $index) use ($data, $comparison) {
            $datasets = [$series];
            if ($comparison) {
                $datasets[] = ['name' => 'Periodo di confronto', 'data' => array_map(
                    fn ($i) => $comparison['series'][$index]['data'][$i] ?? null, array_keys($data['labels'])
                )];
            }
            $chart = (new LarapexChart)->lineChart()->setHeight(330)->setXAxis($data['labels'])
                ->setDataset($datasets)->setColors([self::COLORS[$index], '#64748b'])
                ->setStroke(3, [self::COLORS[$index], '#64748b'])->setMarkers([], 4, 6)->setGrid()
                ->setToolbar(true)->setShowLegend((bool) $comparison);

            return $this->options($chart);
        }, $data['series'], array_keys($data['series']));
    }

    public function distributions(FinanceReportQuery $reports, array $filters, string $group, bool $detail): array
    {
        $result = [];
        if (! $detail) {
            $kind = $group === 'projects' ? 'projects' : 'clients';
            $name = $kind === 'projects' ? 'project_name' : 'client_name';
            $key = $kind === 'projects' ? 'project_id' : 'client_id';
            $rows = $reports->groups($kind, $filters)->where('metrics.total_invoiced', '!=', 0)
                ->orderByDesc('total_invoiced')->orderBy($key)->limit(8)->get();
            $entries = $rows->map(fn ($row) => ['name' => $row->$name, 'amount' => round((float) $row->total_invoiced, 2),
                'url' => route('economic-summary.show', array_merge(Arr::except($filters, ['page', 'documents_page', 'directory_search']), ['group' => $kind, 'id' => $row->$key]))]);
            $chart = (new LarapexChart)->horizontalBarChart()->setHeight(330)->setXAxis($entries->pluck('name')->map(fn ($value) => e($value))->all())
                ->setDataset([['name' => 'Fatturato netto', 'data' => $entries->pluck('amount')->all()]])
                ->setColors([self::COLORS[0]])->setShowLegend(false)->setDataLabels(false);
            $result[] = ['title' => $kind === 'projects' ? 'Primi 8 progetti per fatturato netto' : 'Primi 8 clienti per fatturato netto',
                'kind' => 'ranking', 'options' => $this->options($chart), 'entries' => $entries->all()];
        }
        $totals = $reports->categoryGroups($filters);
        $categories = (clone $totals)->orderByDesc('total_expenses')->orderBy('expenses.category_id')->limit(5)->get();
        $entries = $categories->map(fn ($row) => ['name' => $row->name, 'amount' => round((float) $row->total_expenses, 2),
            'url' => route('expenses.index', array_filter([
                'category_id' => $row->category_id ?? 'none', 'from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null,
                'client_id' => $filters['client_id'] ?? null, 'project_id' => $filters['project_id'] ?? null,
                'subcategory_id' => $filters['subcategory_id'] ?? null, 'status' => $filters['expense_status'] ?? null,
                'search' => $filters['q'] ?? null, 'min_amount' => $filters['min_amount'] ?? null, 'max_amount' => $filters['max_amount'] ?? null,
            ], fn ($value) => filled($value)))])->values();
        $overall = (float) \Illuminate\Support\Facades\DB::query()->fromSub($totals, 'category_totals')->sum('total_expenses');
        $remaining = round($overall - $entries->sum('amount'), 2);
        if ($remaining > 0) {
            $entries->push(['name' => 'Altre categorie', 'amount' => $remaining, 'url' => null]);
        }
        $chart = (new LarapexChart)->donutChart()->setHeight(330)->setLabels($entries->pluck('name')->map(fn ($value) => e($value))->all())
            ->setDataset($entries->pluck('amount')->all())->setColors(self::COLORS)->setShowLegend(true)->setDataLabels(false);
        $result[] = ['title' => 'Distribuzione delle spese', 'kind' => 'expenses', 'options' => $this->options($chart), 'entries' => $entries->all()];

        return $result;
    }

    private function options(LarapexChart $chart): array
    {
        /** Use the local ApexCharts bundle and safely serialize data instead of the package's inline scripts or CDN. */
        $data = $chart->toVue();
        $options = $data['options'];
        $options['chart']['type'] = $data['type'];
        $options['chart']['fontFamily'] = 'inherit';
        $options['series'] = $data['series'];
        $options['legend']['show'] = $options['legend']['show'] === 'true';
        return $options;
    }
}
