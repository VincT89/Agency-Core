<?php

namespace App\Http\Controllers;

use App\Domain\Quotes\ImproveQuoteText;
use App\Http\Requests\QuoteTextSuggestionRequest;
use Illuminate\Http\JsonResponse;

class QuoteTextSuggestionController extends Controller
{
    public function __invoke(QuoteTextSuggestionRequest $request, ImproveQuoteText $action): JsonResponse
    {
        return response()->json($action->execute($request->validated()))->header('Cache-Control', 'private, no-store');
    }
}
