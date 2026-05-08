<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class InvoiceItemController extends Controller
{
    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'description' => 'required|string|max:255',
            'quantity'    => 'required|numeric|min:0.01',
            'unit_price'  => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator, 'invoice_items')->withInput();
        }

        $data = $validator->validated();

        $total = $data['quantity'] * $data['unit_price'];

        $invoice->items()->create([
            'billable_type' => null,
            'billable_id'   => null,
            'description'   => $data['description'],
            'quantity'      => $data['quantity'],
            'unit_price'    => $data['unit_price'],
            'total'         => $total,
        ]);

        // Ricalcola subtotal e total
        $newSubtotal = $invoice->items()->sum('total');
        $invoice->update([
            'subtotal' => $newSubtotal,
            'total'    => $newSubtotal + $invoice->tax_amount,
        ]);

        return back()->with('success', 'Voce aggiunta correttamente.');
    }

    public function destroy(Invoice $invoice, InvoiceItem $item): RedirectResponse
    {
        $this->authorize('update', $invoice);

        // Sicurezza: solo voci libere eliminabili, non quelle collegate
        if ($item->billable_type !== null) {
            abort(403, 'Le voci collegate a contratti o extra non possono essere eliminate.');
        }

        // Sicurezza: la voce deve appartenere a questa fattura
        if ($item->invoice_id !== $invoice->id) {
            abort(403);
        }

        $item->delete();

        $newSubtotal = $invoice->items()->sum('total');
        $invoice->update([
            'subtotal' => $newSubtotal,
            'total'    => $newSubtotal + $invoice->tax_amount,
        ]);

        return back()->with('success', 'Voce rimossa correttamente.');
    }
}
