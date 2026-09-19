<?php

namespace App\Http\Controllers;

use App\Domain\Finance\Actions\StoreExpenseDocument;
use App\Domain\Finance\Services\CashAmount;
use App\Http\Requests\StoreExpenseDocumentRequest;
use App\Models\Expense;
use App\Models\ExpenseDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExpenseDocumentController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:255']]);
        $documents = ExpenseDocument::withCount('expenses')
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($match) => $match->where('issuer', 'like', '%'.$search.'%')->orWhere('number', 'like', '%'.$search.'%')))
            ->latest('document_date')->orderByDesc('id')->paginate(20)->withQueryString();

        return view('expenses.documents.index', compact('documents'));
    }

    public function create()
    {
        return view('expenses.documents.create');
    }

    public function store(StoreExpenseDocumentRequest $request, StoreExpenseDocument $store)
    {
        $data = $request->safe()->except('file');
        $file = $request->file('file');
        $document = $store->execute($data, $file->getContent(), $file->getClientOriginalName());

        return redirect()->route('expenses.documents.show', $document)->with('success', 'Documento disponibile. Collegalo a una spesa esistente oppure registra una nuova uscita.');
    }

    public function show(Request $request, ExpenseDocument $document)
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:255']]);
        $document->load('expenses');
        $allocated = $document->expenses->where('status', '!=', 'cancelled')->sum(fn ($expense) => CashAmount::cents($expense->amount));
        $remaining = max(0, CashAmount::cents($document->amount) - $allocated);
        $candidates = Expense::where('status', 'pending')->whereNull('expense_document_id')->where('document_kind', $document->kind)
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($match) => $match->where('title', 'like', '%'.$search.'%')->orWhere('supplier', 'like', '%'.$search.'%')))
            ->orderBy('due_date')->orderBy('id')->paginate(10)->withQueryString();

        return view('expenses.documents.show', compact('document', 'allocated', 'remaining', 'candidates'));
    }

    public function download(ExpenseDocument $document)
    {
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        return Storage::disk($document->disk)->download($document->path, $document->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }
}
