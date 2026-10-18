<?php

namespace App\Http\Controllers;

use App\Services\EconomicSummaryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EconomicSummaryController extends Controller
{
    public function __invoke(Request $request, EconomicSummaryService $economicSummaryService, \App\Services\FinancialSummaryService $financialService): View
    {
        $user = auth()->user();

        // Blocca l'accesso ai profili operativi non autorizzati all'area finance
        abort_if(! $user->canAccessFinance(), 403);

        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        $globalSummary = $economicSummaryService->globalSummary($user, $from, $to);
        $summaryByClient = $economicSummaryService->summaryByClient($user, $from, $to);
        $summaryByProject = $economicSummaryService->summaryByProject($user, $from, $to);
        $chartData = $financialService->getReportMonthlyData($from, $to);

        return view('economic-summary.index', [
            'from' => $from,
            'to' => $to,
            'globalSummary' => $globalSummary,
            'summaryByClient' => $summaryByClient,
            'summaryByProject' => $summaryByProject,
            'sparklineData' => json_encode(['labels' => $chartData['labels'],
                'invoiced' => $chartData['series'][0]['data'], 'collected' => $chartData['series'][1]['data'],
                'pending' => $chartData['series'][2]['data']], JSON_THROW_ON_ERROR),
        ]);
    }
}
