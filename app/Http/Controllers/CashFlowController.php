<?php

namespace App\Http\Controllers;

use App\Domain\Finance\Services\CashFlowForecast;
use App\Models\CashFlowSetting;
use Illuminate\Http\Request;

class CashFlowController extends Controller
{
    public function index(Request $request, CashFlowForecast $forecast)
    {
        $filters = $request->validate(['months' => ['nullable', 'integer', 'in:6,12,24']]);
        $months = (int) ($filters['months'] ?? 12);
        $setting = CashFlowSetting::find(1);

        return view('expenses.forecast', compact('setting', 'months') + $forecast->calculate($months, $setting));
    }

    public function updateBalance(Request $request)
    {
        $data = $request->validate([
            'opening_balance' => ['required', 'numeric', 'decimal:0,2', 'between:-999999999999.99,999999999999.99'],
            'balance_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);
        CashFlowSetting::updateOrCreate(['id' => 1], $data + ['updated_by' => $request->user()->id]);

        return redirect()->route('expenses.forecast')->with('success', 'Saldo iniziale aggiornato.');
    }
}
