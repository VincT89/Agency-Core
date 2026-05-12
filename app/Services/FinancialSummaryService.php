<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Expense;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialSummaryService
{
    /**
     * Get monthly financial data for charts
     */
    public function getMonthlyData(int $months = 12): array
    {
        $startDate = now()->startOfYear();
        $months = 12;
        
        $monthsArray = [];
        $labels = [];
        for ($i = 0; $i < $months; $i++) {
            $date = $startDate->copy()->addMonths($i);
            $key = $date->format('Y-m');
            $monthsArray[$key] = [
                'issued' => 0,
                'collected' => 0,
                'expenses' => 0,
            ];
            $labels[] = ucfirst($date->isoFormat('MMM YYYY'));
        }

        // Fatturato (Invoices issued/partially_paid/paid/overdue)
        $invoices = Invoice::where('issue_date', '>=', $startDate)
            ->whereIn('status', ['issued', 'partially_paid', 'paid', 'overdue'])
            ->selectRaw('DATE_FORMAT(issue_date, "%Y-%m") as month, SUM(total) as total')
            ->groupBy('month')
            ->get();

        foreach ($invoices as $inv) {
            if (isset($monthsArray[$inv->month])) {
                $monthsArray[$inv->month]['issued'] = (float) $inv->total;
            }
        }

        // Incassato (Payments)
        $payments = Payment::where('payment_date', '>=', $startDate)
            ->selectRaw('DATE_FORMAT(payment_date, "%Y-%m") as month, SUM(amount) as total')
            ->groupBy('month')
            ->get();

        foreach ($payments as $pay) {
            if (isset($monthsArray[$pay->month])) {
                $monthsArray[$pay->month]['collected'] = (float) $pay->total;
            }
        }

        // Spese pagate (Expenses)
        $expenses = Expense::where('paid_at', '>=', $startDate)
            ->where('status', 'paid')
            ->selectRaw('DATE_FORMAT(paid_at, "%Y-%m") as month, SUM(amount) as total')
            ->groupBy('month')
            ->get();

        foreach ($expenses as $exp) {
            if (isset($monthsArray[$exp->month])) {
                $monthsArray[$exp->month]['expenses'] = (float) $exp->total;
            }
        }

        $issuedData = [];
        $collectedData = [];
        $expensesData = [];

        foreach ($monthsArray as $data) {
            $issuedData[] = $data['issued'];
            $collectedData[] = $data['collected'];
            $expensesData[] = $data['expenses'];
        }

        return [
            'labels' => $labels,
            'series' => [
                [
                    'name' => 'Fatturato',
                    'data' => $issuedData
                ],
                [
                    'name' => 'Incassato',
                    'data' => $collectedData
                ],
                [
                    'name' => 'Spese',
                    'data' => $expensesData
                ]
            ]
        ];
    }

    /**
     * Get donut chart data (Incassato vs Da Incassare) for the current year
     */
    public function getYearlyDonutData(): array
    {
        $startDate = now()->startOfYear();

        $invoices = Invoice::where('issue_date', '>=', $startDate)
            ->whereIn('status', ['issued', 'partially_paid', 'paid', 'overdue'])
            ->selectRaw('SUM(paid_total) as collected, SUM(total - paid_total) as pending')
            ->first();

        $collected = (float) ($invoices->collected ?? 0);
        $pending = (float) ($invoices->pending ?? 0);

        return [
            'labels' => ['Incassato', 'Da Incassare'],
            'series' => [$collected, $pending]
        ];
    }

    /**
     * Get monthly collected vs pending data for line chart
     */
    public function getIncassatoVsDaIncassareMonthlyData(int $months = 12): array
    {
        $startDate = now()->startOfYear();
        $months = 12;
        
        $monthsArray = [];
        $labels = [];
        for ($i = 0; $i < $months; $i++) {
            $date = $startDate->copy()->addMonths($i);
            $key = $date->format('Y-m');
            $monthsArray[$key] = [
                'invoiced' => 0,
                'collected' => 0,
                'pending' => 0,
            ];
            $labels[] = ucfirst($date->isoFormat('MMM YYYY'));
        }

        // We can approximate pending by month based on invoice issue date
        $invoices = Invoice::where('issue_date', '>=', $startDate)
            ->whereIn('status', ['issued', 'partially_paid', 'paid', 'overdue'])
            ->selectRaw('DATE_FORMAT(issue_date, "%Y-%m") as month, SUM(total) as invoiced, SUM(paid_total) as collected, SUM(total - paid_total) as pending')
            ->groupBy('month')
            ->get();

        foreach ($invoices as $inv) {
            if (isset($monthsArray[$inv->month])) {
                $monthsArray[$inv->month]['invoiced'] = (float) $inv->invoiced;
                $monthsArray[$inv->month]['collected'] = (float) $inv->collected;
                $monthsArray[$inv->month]['pending'] = (float) $inv->pending;
            }
        }

        $invoicedData = [];
        $collectedData = [];
        $pendingData = [];

        foreach ($monthsArray as $data) {
            $invoicedData[] = $data['invoiced'];
            $collectedData[] = $data['collected'];
            $pendingData[] = $data['pending'];
        }

        return [
            'labels' => $labels,
            'series' => [
                [
                    'name' => 'Fatturato',
                    'data' => $invoicedData
                ],
                [
                    'name' => 'Incassato',
                    'data' => $collectedData
                ],
                [
                    'name' => 'Da Incassare',
                    'data' => $pendingData
                ]
            ]
        ];
    }

    /**
     * Get Sparkline Data for the last 6 months
     */
    public function getSparklineData(int $months = 6): array
    {
        $monthlyData = $this->getIncassatoVsDaIncassareMonthlyData($months);
        
        return [
            'labels' => $monthlyData['labels'],
            'invoiced' => $monthlyData['series'][0]['data'],
            'collected' => $monthlyData['series'][1]['data'],
            'pending' => $monthlyData['series'][2]['data'],
        ];
    }
}
