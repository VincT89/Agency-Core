<?php

namespace App\Http\Controllers;

use App\Domain\Finance\Actions\GenerateRecurringExpenses;
use App\Http\Requests\StoreExpenseRecurrenceRequest;
use App\Models\ExpenseRecurrence;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ExpenseRecurrenceController extends Controller
{
    public function index()
    {
        return view('expenses.recurrences.index', ['recurrences' => ExpenseRecurrence::orderByDesc('active')->orderBy('title')->paginate(20)]);
    }

    public function create()
    {
        return view('expenses.recurrences.form', ['recurrence' => new ExpenseRecurrence(['starts_on' => today(), 'frequency' => 'monthly', 'document_kind' => 'invoice', 'active' => true])]);
    }

    public function store(StoreExpenseRecurrenceRequest $request, GenerateRecurringExpenses $generator)
    {
        $recurrence = DB::transaction(function () use ($request, $generator) {
            $recurrence = ExpenseRecurrence::create($request->validated() + ['user_id' => $request->user()->id]);
            $generator->execute($recurrence);

            return $recurrence;
        });

        return redirect()->route('expenses.recurrences.edit', $recurrence)->with('success', 'Ricorrenza creata. Le uscite previste sono disponibili nelle spese.');
    }

    public function edit(ExpenseRecurrence $recurrence)
    {
        return view('expenses.recurrences.form', compact('recurrence'));
    }

    public function update(StoreExpenseRecurrenceRequest $request, ExpenseRecurrence $recurrence, GenerateRecurringExpenses $generator)
    {
        DB::transaction(function () use ($request, $recurrence, $generator) {
            $recurrence = ExpenseRecurrence::lockForUpdate()->findOrFail($recurrence->id);
            $resuming = ! $recurrence->active && $request->boolean('active');
            $recurrence->update($request->validated());
            $generator->updateFuture($recurrence);
            $generator->execute($recurrence, cancelMissingBefore: $resuming ? CarbonImmutable::today() : null);
        });

        return back()->with('success', 'Ricorrenza aggiornata. Lo storico e le scadenze già documentate o modificate sono stati conservati.');
    }
}
