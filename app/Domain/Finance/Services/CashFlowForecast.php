<?php

namespace App\Domain\Finance\Services;

use App\Models\CashFlowSetting;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\ManualIncome;
use App\Models\Payment;
use Carbon\CarbonImmutable;

class CashFlowForecast
{
    public function calculate(int $months, ?CashFlowSetting $setting): array
    {
        $start = $setting?->balance_date ?? CarbonImmutable::today();
        $end = $start->startOfMonth()->addMonths($months - 1)->endOfMonth();
        $rows = [];
        for ($month = $start->startOfMonth(); $month->lte($end); $month = $month->addMonth()) {
            $rows[$month->format('Y-m')] = ['month' => $month, 'received' => 0, 'expected' => 0, 'paid' => 0, 'pending' => 0];
        }
        $overdue = ['incoming' => 0, 'outgoing' => 0];
        $missingDueDates = 0;
        $add = function ($date, string $type, int $amount, bool $forecast = false) use (&$rows, &$overdue, $start, $end) {
            $date = CarbonImmutable::parse($date)->startOfDay();
            if ($amount <= 0 || $date->gt($end) || (! $forecast && $date->lt($start))) {
                return;
            }
            if ($forecast && $date->lt($start)) {
                $overdue[$type === 'expected' ? 'incoming' : 'outgoing'] += $amount;
                $date = $start;
            }
            $rows[$date->format('Y-m')][$type] += $amount;
        };

        $excludedCurrencies = Invoice::where('currency', '!=', 'EUR')->whereIn('status', ['issued', 'partially_paid', 'overdue', 'paid'])->count();
        foreach (Payment::whereHas('invoice', fn ($query) => $query->where('currency', 'EUR'))->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()])->cursor() as $payment) {
            $add($payment->payment_date, 'received', CashAmount::cents($payment->amount));
        }
        foreach (Invoice::where('currency', 'EUR')->whereIn('status', ['issued', 'partially_paid', 'overdue'])->cursor() as $invoice) {
            $residual = max(0, CashAmount::cents($invoice->total) - CashAmount::cents($invoice->paid_total));
            if ($residual > 0 && ! $invoice->due_date) {
                $missingDueDates++;
            }
            $add($invoice->due_date ?? $start, 'expected', $residual, true);
        }
        foreach (Expense::whereIn('status', ['pending', 'paid'])->cursor() as $expense) {
            $paid = $expense->status === 'paid';
            $date = $paid ? ($expense->paid_at ?? $expense->expense_date) : ($expense->due_date ?? $expense->expense_date);
            $add($date, $paid ? 'paid' : 'pending', CashAmount::cents($expense->amount), ! $paid);
        }
        foreach (ManualIncome::whereIn('status', ['expected', 'received'])->cursor() as $income) {
            $received = $income->status === 'received';
            $add($received ? $income->received_on : $income->expected_on, $received ? 'received' : 'expected', CashAmount::cents($income->amount), ! $received);
        }

        $balance = $setting ? CashAmount::cents($setting->opening_balance) : null;
        $totals = ['received' => 0, 'expected' => 0, 'paid' => 0, 'pending' => 0];
        foreach ($rows as &$row) {
            foreach (array_keys($totals) as $field) {
                $totals[$field] += $row[$field];
            }
            $row['net'] = $row['received'] + $row['expected'] - $row['paid'] - $row['pending'];
            if ($balance !== null) {
                $balance += $row['net'];
            }
            $row['closing_balance'] = $balance;
        }
        unset($row);

        return compact('rows', 'totals', 'start', 'end', 'overdue', 'missingDueDates', 'excludedCurrencies');
    }
}
