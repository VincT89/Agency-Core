<?php

namespace App\Http\Controllers;

use App\Domain\Quotes\QuoteAmounts;
use App\Http\Requests\QuoteServiceRequest;
use App\Models\Quote;
use App\Models\QuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuoteServiceController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('create', Quote::class);
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $services = QuoteService::query()->when($data['q'] ?? '', fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))
            ->orderBy('name')->orderBy('id');
        if ($request->expectsJson()) {
            return response()->json($services->limit(30)->get(['id', ...QuoteService::FIELDS]));
        }

        return view('quotes.services', ['services' => $services->paginate(20)->withQueryString()]);
    }

    public function store(QuoteServiceRequest $request): JsonResponse
    {
        $data = array_replace(array_fill_keys(QuoteService::FIELDS, null), $request->validated());
        $data['quantity'] = QuoteAmounts::decimal(QuoteAmounts::hundredths((string) $data['quantity']));
        $data['unit_price'] = QuoteAmounts::decimal(QuoteAmounts::hundredths((string) $data['unit_price']));
        $fingerprint = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
        $service = QuoteService::firstOrCreate(['fingerprint' => $fingerprint], $data + ['created_by' => $request->user()->id]);

        return response()->json(['message' => 'Voce disponibile nella libreria.', 'service' => $service->only(['id', ...QuoteService::FIELDS])]);
    }

    public function destroy(QuoteService $service): RedirectResponse
    {
        $this->authorize('create', Quote::class);
        $service->delete();

        return back()->with('success', 'Voce rimossa dalla libreria. I preventivi esistenti restano invariati.');
    }
}
