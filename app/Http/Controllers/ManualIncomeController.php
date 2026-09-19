<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreManualIncomeRequest;
use App\Models\Client;
use App\Models\ManualIncome;
use Illuminate\Http\Request;

class ManualIncomeController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['status' => ['nullable', 'in:expected,received,cancelled']]);
        $incomes = ManualIncome::with('client:id,name')->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('expected_on')->orderByDesc('id')->paginate(20)->withQueryString();

        return view('expenses.incomes.index', compact('incomes'));
    }

    public function create()
    {
        return $this->form(new ManualIncome(['expected_on' => today(), 'status' => 'expected']));
    }

    public function edit(ManualIncome $income)
    {
        return $this->form($income);
    }

    private function form(ManualIncome $income)
    {
        return view('expenses.incomes.form', ['income' => $income, 'clients' => Client::orderBy('name')->get(['id', 'name'])]);
    }

    public function store(StoreManualIncomeRequest $request)
    {
        $data = $request->validated();
        $data['received_on'] = $data['status'] === 'received' ? $data['received_on'] : null;
        ManualIncome::create($data + ['user_id' => $request->user()->id]);

        return redirect()->route('expenses.incomes.index')->with('success', 'Entrata registrata.');
    }

    public function update(StoreManualIncomeRequest $request, ManualIncome $income)
    {
        $data = $request->validated();
        $data['received_on'] = $data['status'] === 'received' ? $data['received_on'] : null;
        $income->update($data);

        return redirect()->route('expenses.incomes.index')->with('success', 'Entrata aggiornata.');
    }
}
