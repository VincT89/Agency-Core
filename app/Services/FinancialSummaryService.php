<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Expense;
use App\Models\Payment;
use Carbon\CarbonImmutable;
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

        /** Turnover includes only invoice taxable amounts, consistently with the economic summary. */
        $dateFormat = DB::connection()->getDriverName() === 'sqlite' ? "strftime('%Y-%m', issue_date)" : "DATE_FORMAT(issue_date, '%Y-%m')";
        $invoices = Invoice::whereBetween('issue_date', [$startDate, $startDate->copy()->endOfYear()])
            ->whereIn('status', ['issued', 'partially_paid', 'paid', 'overdue', 'credited'])
            ->selectRaw("{$dateFormat} as month, SUM(subtotal) as total")
            ->groupBy('month')
            ->get();

        foreach ($invoices as $inv) {
            if (isset($monthsArray[$inv->month])) {
                $monthsArray[$inv->month]['issued'] = (float) $inv->total;
            }
        }

        // Incassato (Payments)
        $paymentFormat = DB::connection()->getDriverName() === 'sqlite' ? "strftime('%Y-%m', payment_date)" : "DATE_FORMAT(payment_date, '%Y-%m')";
        $payments = Payment::whereBetween('payment_date', [$startDate, $startDate->copy()->endOfYear()])
            ->selectRaw("{$paymentFormat} as month, SUM(amount) as total")
            ->groupBy('month')
            ->get();

        foreach ($payments as $pay) {
            if (isset($monthsArray[$pay->month])) {
                $monthsArray[$pay->month]['collected'] = (float) $pay->total;
            }
        }

        // Spese pagate (Expenses)
        $expenseFormat = DB::connection()->getDriverName() === 'sqlite' ? "strftime('%Y-%m', paid_at)" : "DATE_FORMAT(paid_at, '%Y-%m')";
        $expenses = Expense::whereBetween('paid_at', [$startDate, $startDate->copy()->endOfYear()])
            ->where('status', 'paid')
            ->selectRaw("{$expenseFormat} as month, SUM(amount) as total")
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
                    'name' => 'Fatturato netto (IVA esclusa)',
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

        $invoices = Invoice::whereBetween('issue_date', [$startDate, $startDate->copy()->endOfYear()])
            ->whereIn('status', ['issued', 'partially_paid', 'paid', 'overdue', 'credited'])
            ->selectRaw('SUM(paid_total) as collected, SUM('.Invoice::OUTSTANDING_SQL.') as pending')
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
        return $this->getReportMonthlyData(now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString());
    }

    public function getReportMonthlyData(?string $from = null, ?string $to = null): array
    {
        return app(FinanceReportQuery::class)->monthly(array_filter(compact('from', 'to')));
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
            'pending' => $monthlyData['series'][4]['data'],
        ];
    }
}
