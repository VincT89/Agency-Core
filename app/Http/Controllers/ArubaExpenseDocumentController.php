<?php

namespace App\Http\Controllers;

use App\Domain\Finance\Actions\ImportArubaExpenseDocument;
use App\Exceptions\Finance\ArubaApiException;
use App\Exceptions\Finance\ArubaConfigurationException;
use App\Models\BillingProfile;
use App\Services\Integrations\Aruba\ArubaInvoiceClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ArubaExpenseDocumentController extends Controller
{
    public function index()
    {
        return view('expenses.documents.aruba', ['result' => null, 'from' => today()->toDateString(), 'to' => today()->toDateString()]);
    }

    public function search(Request $request, ArubaInvoiceClient $client)
    {
        $data = $request->validate(['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from', 'page' => 'nullable|integer|min:1|max:10000']);
        $from = CarbonImmutable::parse($data['from'])->startOfDay();
        $to = CarbonImmutable::parse($data['to'])->endOfDay();
        if ($from->diffInSeconds($to) > 172800) {
            throw ValidationException::withMessages(['to' => 'Seleziona al massimo due giorni consecutivi di ricezione.']);
        }
        $profile = BillingProfile::current();
        if (! $profile?->vat_number || ! $profile->vat_country_code) {
            return back()->withInput()->withErrors(['aruba' => 'Completa prima i dati fiscali dell’agenzia.']);
        }
        try {
            $result = $client->receivedInvoices($from->toIso8601String(), $to->toIso8601String(), (int) ($data['page'] ?? 1), $profile->vat_country_code, $profile->vat_number);
        } catch (ArubaApiException|ArubaConfigurationException $exception) {
            return back()->withInput()->withErrors(['aruba' => $exception->getMessage()]);
        }
        if (! isset($result['content']) || ! is_array($result['content'])) {
            return back()->withInput()->withErrors(['aruba' => 'Aruba non ha restituito un elenco leggibile. Riprova più tardi.']);
        }

        return view('expenses.documents.aruba', ['result' => $result, 'from' => $data['from'], 'to' => $data['to']]);
    }

    public function store(Request $request, ImportArubaExpenseDocument $import)
    {
        $data = $request->validate(['invoice_id' => 'required|string|max:100', 'body_index' => 'required|integer|min:0|max:999']);
        try {
            $document = $import->execute($data['invoice_id'], (int) $data['body_index']);
        } catch (ArubaApiException|ArubaConfigurationException $exception) {
            return back()->withErrors(['aruba' => $exception->getMessage()]);
        }

        return redirect()->route('expenses.documents.show', $document)
            ->with('success', 'Fattura acquisita da Aruba. Collegala alla spesa corrispondente per completare la registrazione.');
    }
}
