<?php

namespace App\Http\Controllers;

use App\Services\EconomicSummaryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EconomicSummaryController extends Controller
{
    public function __invoke(Request $request, EconomicSummaryService $economicSummaryService): View
    {
        $user = auth()->user();

        // Operativi and staff not allowed in finance dashboard
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
        ]);
    }
}