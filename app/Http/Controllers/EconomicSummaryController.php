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

        $from = $request->string('from')->toString() ?: null;
        $to = $request->string('to')->toString() ?: null;

        $globalSummary = $economicSummaryService->globalSummary($user, $from, $to);
        $summaryByClient = $economicSummaryService->summaryByClient($user, $from, $to);
        $summaryByProject = $economicSummaryService->summaryByProject($user, $from, $to);

        return view('economic-summary.index', [
            'from' => $from,
            'to' => $to,
            'globalSummary' => $globalSummary,
            'summaryByClient' => $summaryByClient,
            'summaryByProject' => $summaryByProject,
            'lineChartData'    => json_encode($financialService->getIncassatoVsDaIncassareMonthlyData(12)),
            'donutChartData'   => json_encode($financialService->getYearlyDonutData()),
            'sparklineData'    => json_encode($financialService->getSparklineData(6)),
        ]);
    }
}